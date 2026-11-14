<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\InboundEventBus;

use Closure;
use CloudCreativity\Modules\Bus\InboundEventDispatcher;
use CloudCreativity\Modules\Bus\PsrPipeContainer;
use CloudCreativity\Modules\Bus\SwallowInboundEvent;
use CloudCreativity\Modules\Contracts\Bus\EventHandler;
use CloudCreativity\Modules\Contracts\Bus\EventHandlerContainer;
use CloudCreativity\Modules\Contracts\Messaging\IntegrationEvent;
use CloudCreativity\Modules\Testing\FakeContainer;
use CloudCreativity\Modules\Testing\FakeLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InboundEventDispatcherTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $sequence = [];

    #[Test]
    public function it_dispatches_an_event(): void
    {
        $event = $this->createStub(IntegrationEvent::class);
        $handler = $this->createMock(EventHandler::class);
        $handlers = $this->createMock(EventHandlerContainer::class);
        $dispatcher = new class ($handlers) extends InboundEventDispatcher {};

        $handlers
            ->expects($this->once())
            ->method('get')
            ->with($event::class)
            ->willReturn($handler);

        $handler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($event));

        $dispatcher->dispatch($event);
    }

    #[Test]
    public function it_dispatches_an_event_through_middleware(): void
    {
        $event1 = new TestInboundEvent();
        $event2 = new TestInboundEvent();
        $event3 = new TestInboundEvent();
        $event4 = new TestInboundEvent();
        $handler = $this->createMock(EventHandler::class);
        $handlers = $this->createMock(EventHandlerContainer::class);

        $middleware1 = function (TestInboundEvent $event, Closure $next) use ($event1, $event2): void {
            $this->assertSame($event1, $event);
            $this->sequence[] = 'before1';
            $next($event2);
            $this->sequence[] = 'after1';
        };

        $middleware2 = function (TestInboundEvent $event, Closure $next) use ($event2, $event3): void {
            $this->assertSame($event2, $event);
            $this->sequence[] = 'before2';
            $next($event3);
            $this->sequence[] = 'after2';
        };

        $middleware3 = function (TestInboundEvent $event, Closure $next) use ($event3, $event4): void {
            $this->assertSame($event3, $event);
            $this->sequence[] = 'before3';
            $next($event4);
            $this->sequence[] = 'after3';
        };

        $middleware = new PsrPipeContainer();
        $middleware->bind('MyFirstMiddleware', fn () => $middleware1);
        $middleware->bind('MySecondMiddleware', fn () => $middleware2);

        $dispatcher = new class ($handlers, $middleware) extends InboundEventDispatcher {};
        $dispatcher->through(['MyFirstMiddleware']);

        $handlers
            ->expects($this->once())
            ->method('get')
            ->with($event1::class)
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
            ->with($this->identicalTo($event4));

        $dispatcher->dispatch($event1);

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
        $event = new TestInboundEvent();
        $handler = $this->createMock(TestEventHandler::class);
        $container = new FakeContainer();
        $container->instance(TestEventHandler::class, $handler);
        $container->instance('Middleware1', function (IntegrationEvent $actual, Closure $next): void {
            $this->sequence[] = 'Middleware1';
            $next($actual);
        });
        $dispatcher = new TestInboundEventDispatcherWithAttributes($container);

        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event))
            ->willReturnCallback(function (): void {
                $this->sequence[] = 'handler';
            });

        $dispatcher->dispatch($event);

        $this->assertSame(['Middleware1', 'handler'], $this->sequence);
    }

    #[Test]
    public function it_autowires_the_default_handler_from_a_psr_container(): void
    {
        $event = $this->createStub(IntegrationEvent::class);
        $logger = new FakeLogger();
        $container = new FakeContainer();
        $container->instance(SwallowInboundEvent::class, new SwallowInboundEvent($logger));
        $container->instance('Middleware1', function (IntegrationEvent $actual, Closure $next): void {
            $this->sequence[] = 'Middleware1';
            $next($actual);
        });
        $dispatcher = new TestInboundEventDispatcherWithAttributes($container);

        $dispatcher->dispatch($event);

        $this->assertSame(['Middleware1'], $this->sequence);
        $this->assertSame([
            'DEBUG: Swallowing inbound integration event ' . $event::class . '.',
        ], $logger->messages());
    }

    #[Test]
    public function it_autowires_middleware_but_not_handlers_with_a_custom_handler_container(): void
    {
        $event = new TestInboundEvent();
        $middleware = new PsrPipeContainer();
        $middleware->bind('Middleware1', fn () => function (IntegrationEvent $actual, Closure $next): void {
            $this->sequence[] = 'Middleware1';
            $next($actual);
        });
        $handler = $this->createMock(EventHandler::class);
        $handlers = $this->createMock(EventHandlerContainer::class);
        $dispatcher = new TestInboundEventDispatcherWithAttributes($handlers, $middleware);

        $handlers
            ->expects($this->once())
            ->method('get')
            ->with(TestInboundEvent::class)
            ->willReturn($handler);

        $handler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($event))
            ->willReturnCallback(function (): void {
                $this->sequence[] = 'handler';
            });

        $dispatcher->dispatch($event);

        $this->assertSame(['Middleware1', 'handler'], $this->sequence);
    }
}
