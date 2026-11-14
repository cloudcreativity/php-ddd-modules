<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\Bus;

use Closure;
use CloudCreativity\Modules\Bus\CommandDispatcher;
use CloudCreativity\Modules\Bus\PsrPipeContainer;
use CloudCreativity\Modules\Contracts\Bus\CommandHandler;
use CloudCreativity\Modules\Contracts\Bus\CommandHandlerContainer;
use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Testing\FakeContainer;
use CloudCreativity\Modules\Toolkit\Result\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandDispatcherTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $sequence = [];

    #[Test]
    public function it_dispatches_a_command(): void
    {
        $command = $this->createStub(Command::class);
        $handler = $this->createMock(CommandHandler::class);
        $handlers = $this->createMock(CommandHandlerContainer::class);
        $dispatcher = new class ($handlers) extends CommandDispatcher {};

        $handlers
            ->expects($this->once())
            ->method('get')
            ->with($command::class)
            ->willReturn($handler);

        $handler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($command))
            ->willReturn($expected = Result::ok());

        $actual = $dispatcher->dispatch($command);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_dispatches_a_command_through_middleware(): void
    {
        $command1 = new TestCommand();
        $command2 = new TestCommand();
        $command3 = new TestCommand();
        $command4 = new TestCommand();
        $handler = $this->createMock(CommandHandler::class);
        $handlers = $this->createMock(CommandHandlerContainer::class);

        $middleware1 = function (TestCommand $command, Closure $next) use ($command1, $command2) {
            $this->assertSame($command1, $command);
            $this->sequence[] = 'before1';
            $result = $next($command2);
            $this->sequence[] = 'after1';
            return $result;
        };

        $middleware2 = function (TestCommand $command, Closure $next) use ($command2, $command3) {
            $this->assertSame($command2, $command);
            $this->sequence[] = 'before2';
            $result = $next($command3);
            $this->sequence[] = 'after2';
            return $result;
        };

        $middleware3 = function (TestCommand $command, Closure $next) use ($command3, $command4) {
            $this->assertSame($command3, $command);
            $this->sequence[] = 'before3';
            $result = $next($command4);
            $this->sequence[] = 'after3';
            return $result;
        };

        $middleware = new PsrPipeContainer();
        $middleware->bind('MyFirstMiddleware', fn () => $middleware1);
        $middleware->bind('MySecondMiddleware', fn () => $middleware2);

        $dispatcher = new class ($handlers, $middleware) extends CommandDispatcher {};
        $dispatcher->through(['MyFirstMiddleware']);

        $handlers
            ->expects($this->once())
            ->method('get')
            ->with($command1::class)
            ->willReturnCallback(function () use ($handler) {
                $this->assertSame(['before1'], $this->sequence);
                return $handler;
            });

        $handler
            ->expects($this->once())
            ->method('middleware')
            ->willReturn(['MySecondMiddleware', $middleware3]);

        $handler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($command4))
            ->willReturn($expected = Result::ok());

        $actual = $dispatcher->dispatch($command1);

        $this->assertSame($expected, $actual);
        $this->assertSame([
            'before1',
            'before2',
            'before3',
            'after3',
            'after2',
            'after1',
        ], $this->sequence);
    }

    #[Test]
    public function it_autowires_handlers_and_middleware_from_a_psr_container(): void
    {
        $command = new TestCommand();
        $expected = Result::ok();
        $handler = $this->createMock(TestCommandHandler::class);
        $container = new FakeContainer();
        $container->instance(TestCommandHandler::class, $handler);
        $container->instance('Middleware1', function (TestCommand $actual, Closure $next) {
            $this->sequence[] = 'Middleware1';
            return $next($actual);
        });
        $dispatcher = new TestCommandDispatcherWithAttributes($container);

        $handler
            ->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo($command))
            ->willReturnCallback(function () use ($expected) {
                $this->sequence[] = 'handler';
                return $expected;
            });

        $actual = $dispatcher->dispatch($command);

        $this->assertSame($expected, $actual);
        $this->assertSame(['Middleware1', 'handler'], $this->sequence);
    }

    #[Test]
    public function it_autowires_middleware_but_not_handlers_with_a_custom_handler_container(): void
    {
        $command = new TestCommand();
        $expected = Result::ok();
        $middleware = new PsrPipeContainer();
        $middleware->bind('Middleware1', fn () => function (TestCommand $actual, Closure $next) {
            $this->sequence[] = 'Middleware1';
            return $next($actual);
        });
        $handler = $this->createMock(CommandHandler::class);
        $handlers = $this->createMock(CommandHandlerContainer::class);
        $dispatcher = new TestCommandDispatcherWithAttributes($handlers, $middleware);

        $handlers
            ->expects($this->once())
            ->method('get')
            ->with(TestCommand::class)
            ->willReturn($handler);

        $handler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($command))
            ->willReturnCallback(function () use ($expected) {
                $this->sequence[] = 'handler';
                return $expected;
            });

        $actual = $dispatcher->dispatch($command);

        $this->assertSame($expected, $actual);
        $this->assertSame(['Middleware1', 'handler'], $this->sequence);
    }
}
