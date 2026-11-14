<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Infrastructure\Queue;

use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Contracts\Toolkit\Pipeline\PipeContainer;
use CloudCreativity\Modules\Infrastructure\Queue\ClosureQueue;
use CloudCreativity\Modules\Tests\Unit\Application\Bus\TestCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClosureQueueTest extends TestCase
{
    /**
     * @var array<Command>
     */
    private array $actual = [];

    private ClosureQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = $this->createQueue($this->createStub(PipeContainer::class));
    }

    protected function tearDown(): void
    {
        unset($this->queue, $this->actual);
        parent::tearDown();
    }

    #[Test]
    public function it_queues_a_command(): void
    {
        $command = $this->createStub(Command::class);

        $this->queue->push($command);

        $this->assertSame([$command], $this->actual);
    }

    #[Test]
    public function it_queues_a_command_through_middleware(): void
    {
        $command1 = $this->createStub(Command::class);
        $command2 = $this->createStub(Command::class);
        $command3 = $this->createStub(Command::class);
        $command4 = $this->createStub(Command::class);

        $middleware1 = function ($command, \Closure $next) use ($command1, $command2) {
            $this->assertSame($command1, $command);
            return $next($command2);
        };

        $middleware2 = function ($command, \Closure $next) use ($command2, $command3) {
            $this->assertSame($command2, $command);
            return $next($command3);
        };

        $middleware3 = function ($command, \Closure $next) use ($command3, $command4) {
            $this->assertSame($command3, $command);
            return $next($command4);
        };

        $middleware = $this->createMock(PipeContainer::class);
        $middleware
            ->expects($this->once())
            ->method('get')
            ->with('MySecondMiddleware')
            ->willReturn($middleware2);

        $this->queue = $this->createQueue($middleware);

        $this->queue->through([
            $middleware1,
            'MySecondMiddleware',
            $middleware3,
        ]);

        $this->queue->push($command1);

        $this->assertSame([$command4], $this->actual);
    }

    #[Test]
    public function it_queues_using_the_handler_bound_to_the_command_class(): void
    {
        $expected = new TestCommand();
        $stub = $this->createStub(Command::class);
        $actual = null;

        $this->queue->bind($stub::class, function (Command $cmd): never {
            $this->fail('Not expecting this closure to be called.');
        });

        $this->queue->bind(
            TestCommand::class,
            function (TestCommand $cmd) use (&$actual) {
                $actual = $cmd;
            },
        );

        $this->queue->push($expected);

        $this->assertEmpty($this->actual);
        $this->assertSame($expected, $actual);
    }

    private function createQueue(PipeContainer $middleware): ClosureQueue
    {
        return new class (
            function (Command $command): void {
                $this->actual[] = $command;
            },
            $middleware,
        ) extends ClosureQueue {};
    }
}
