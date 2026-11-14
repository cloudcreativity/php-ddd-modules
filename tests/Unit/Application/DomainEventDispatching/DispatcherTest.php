<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\DomainEventDispatching;

use Closure;
use CloudCreativity\Modules\Application\DomainEventDispatching\Dispatcher;
use CloudCreativity\Modules\Application\DomainEventDispatching\ListenerContainer;
use CloudCreativity\Modules\Bus\PsrPipeContainer;
use CloudCreativity\Modules\Contracts\Application\DomainEventDispatching\ListenerContainer as IListenerContainer;
use CloudCreativity\Modules\Contracts\Domain\Events\DomainEvent;
use CloudCreativity\Modules\Contracts\Domain\Events\DomainEventDispatcher;
use CloudCreativity\Modules\Testing\FakeContainer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DispatcherTest extends TestCase
{
    #[Test]
    public function it_is_a_domain_event_dispatcher(): void
    {
        $dispatcher = new class () extends Dispatcher {};

        $this->assertInstanceOf(DomainEventDispatcher::class, $dispatcher);
    }

    #[Test]
    public function it_dispatches_to_a_list_of_listeners(): void
    {
        $sequence = [];
        $event1 = new TestImmediateDomainEvent();
        $event2 = new class () extends TestDomainEvent {};

        $listener1 = $this->createMock(TestListener::class);
        $listener2 = $this->createMock(TestListener::class);
        $listener3 = $this->createMock(TestListener::class);
        $listener2Closure = static fn (DomainEvent $event) => $listener2->handle($event);

        $listeners = new ListenerContainer();
        $listeners->bind('Listener1', fn () => $listener1);
        $listeners->bind('Listener3', fn () => $listener3);

        $dispatcher = new class ($listeners) extends Dispatcher {};
        $dispatcher->listen($event1::class, ['Listener1', $listener2Closure]);
        $dispatcher->listen($event2::class, ['Listener3']);

        $listener1
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event1))
            ->willReturnCallback(function () use (&$sequence) {
                $sequence[] = 'Listener1';
            });

        $listener2
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event1))
            ->willReturnCallback(function () use (&$sequence) {
                $sequence[] = 'Listener2';
            });

        $listener3
            ->expects($this->never())
            ->method('handle');

        $dispatcher->dispatch($event1);

        $this->assertSame(['Listener1', 'Listener2'], $sequence);
    }

    #[Test]
    public function it_dispatches_to_a_single_listener(): void
    {
        $event1 = new class () extends TestDomainEvent {};
        $event2 = new TestImmediateDomainEvent();

        $listener1 = $this->createMock(TestListener::class);
        $listener2 = $this->createMock(TestListener::class);

        $listeners = new ListenerContainer();
        $listeners->bind('Listener1', fn () => $listener1);
        $listeners->bind('Listener2', fn () => $listener2);

        $dispatcher = new class ($listeners) extends Dispatcher {};
        $dispatcher->listen($event1::class, 'Listener1');
        $dispatcher->listen($event2::class, 'Listener2');

        $listener1
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event1));

        $listener2
            ->expects($this->never())
            ->method('handle');

        $dispatcher->dispatch($event1);
    }

    #[Test]
    public function it_does_nothing_when_there_are_no_listeners(): void
    {
        $event = $this->createStub(DomainEvent::class);
        $listeners = $this->createMock(IListenerContainer::class);
        $dispatcher = new class ($listeners) extends Dispatcher {};

        $listeners
            ->expects($this->never())
            ->method($this->anything());

        $dispatcher->dispatch($event);
    }

    #[Test]
    public function it_dispatches_through_middleware(): void
    {
        $event1 = new class () extends TestDomainEvent {};
        $event2 = new class () extends TestDomainEvent {};
        $event3 = new TestImmediateDomainEvent();

        $a = function ($actual, Closure $next) use ($event1, $event2): DomainEvent {
            $this->assertSame($event1, $actual);
            /** @phpstan-ignore-next-line */
            return $next($event2);
        };

        $b = function ($actual, Closure $next) use ($event2, $event3): DomainEvent {
            $this->assertSame($event2, $actual);
            /** @phpstan-ignore-next-line */
            return $next($event3);
        };

        $listener = $this->createMock(TestListener::class);

        $listeners = new ListenerContainer();
        $listeners->bind('MyListener', fn () => $listener);

        $middleware = new PsrPipeContainer();
        $middleware->bind('Middleware2', fn () => $b);

        $dispatcher = new class ($listeners, $middleware) extends Dispatcher {};
        $dispatcher->through([$a, 'Middleware2']);
        $dispatcher->listen($event3::class, 'MyListener');

        $listener
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event3));

        $dispatcher->dispatch($event1);
    }

    #[Test]
    public function it_throws_when_the_listener_is_an_empty_string(): void
    {
        $dispatcher = new class () extends Dispatcher {};

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expecting listener to be a Closure or non-empty string.');

        $dispatcher->listen(TestImmediateDomainEvent::class, ['Listener1', '']);
    }

    #[Test]
    public function it_autowires_listeners_and_middleware_from_attributes(): void
    {
        $sequence = [];
        $event = new TestImmediateDomainEvent();
        $middleware = new PsrPipeContainer();
        $middleware->bind('Middleware1', function () use (&$sequence): Closure {
            return function (DomainEvent $actual, Closure $next) use (&$sequence) {
                $sequence[] = 'Middleware1';
                return $next($actual);
            };
        });

        $listeners = new ListenerContainer();

        foreach (['Listener1', 'Listener2', 'Listener3'] as $name) {
            $listeners->bind($name, function () use (&$sequence, $event, $name): Closure {
                return function (DomainEvent $actual) use (&$sequence, $event, $name): void {
                    $this->assertSame($event, $actual);
                    $sequence[] = $name;
                };
            });
        }

        $dispatcher = new TestDispatcherWithAttributes($listeners, $middleware);

        $dispatcher->dispatch($event);

        $this->assertSame(['Middleware1', 'Listener1', 'Listener2', 'Listener3'], $sequence);
    }

    #[Test]
    public function it_resolves_listeners_and_middleware_from_a_psr_container(): void
    {
        $sequence = [];
        $event = new TestImmediateDomainEvent();
        $container = new FakeContainer();
        $container->instance('Middleware1', function (DomainEvent $actual, Closure $next) use (&$sequence) {
            $sequence[] = 'Middleware1';
            return $next($actual);
        });

        foreach (['Listener1', 'Listener2', 'Listener3'] as $name) {
            $container->instance($name, function (DomainEvent $actual) use (&$sequence, $event, $name): void {
                $this->assertSame($event, $actual);
                $sequence[] = $name;
            });
        }

        $dispatcher = new TestDispatcherWithAttributes($container);

        $dispatcher->dispatch($event);

        $this->assertSame(['Middleware1', 'Listener1', 'Listener2', 'Listener3'], $sequence);
    }
}
