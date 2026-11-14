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
use CloudCreativity\Modules\Application\DomainEventDispatching\DeferredDispatcher;
use CloudCreativity\Modules\Application\DomainEventDispatching\ListenerContainer;
use CloudCreativity\Modules\Bus\PsrPipeContainer;
use CloudCreativity\Modules\Contracts\Application\DomainEventDispatching\DeferredDispatcher as IDeferredDispatcher;
use CloudCreativity\Modules\Contracts\Application\DomainEventDispatching\ListenerContainer as IListenerContainer;
use CloudCreativity\Modules\Contracts\Domain\Events\DomainEvent;
use CloudCreativity\Modules\Contracts\Toolkit\Pipeline\PipeContainer;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeferredDispatcherTest extends TestCase
{
    #[Test]
    public function it_is_a_deferred_dispatcher(): void
    {
        $dispatcher = $this->makeDispatcher();

        $this->assertInstanceOf(IDeferredDispatcher::class, $dispatcher);
    }

    #[Test]
    public function it_dispatches_immediately(): void
    {
        $sequence = [];
        $event = new TestImmediateDomainEvent();

        $listener1 = $this->createMock(TestListener::class);
        $listener2 = $this->createMock(TestListener::class);
        $listener3 = $this->createMock(TestListener::class);
        $listener4 = $this->createMock(TestListener::class);
        $listener2Closure = static fn (DomainEvent $event) => $listener2->handle($event);

        $dispatcher = $this->makeDispatcher([
            'Listener1' => $listener1,
            'Listener3' => $listener3,
            'Listener4' => $listener4,
        ]);
        $dispatcher->listen($event::class, ['Listener1', $listener2Closure, 'Listener3']);
        $dispatcher->listen(TestDomainEvent::class, 'Listener4');

        $listener1
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event))
            ->willReturnCallback(function () use (&$sequence) {
                $sequence[] = 'Listener1';
            });

        $listener2
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event))
            ->willReturnCallback(function () use (&$sequence) {
                $sequence[] = 'Listener2';
            });

        $listener3
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event))
            ->willReturnCallback(function () use (&$sequence) {
                $sequence[] = 'Listener3';
            });

        $listener4
            ->expects($this->never())
            ->method('handle');

        $dispatcher->dispatch($event);

        $this->assertSame(['Listener1', 'Listener2', 'Listener3'], $sequence);
    }

    #[Test]
    public function it_defers_events(): void
    {
        $event = new class () extends TestDomainEvent {};
        $listener = $this->createMock(TestListener::class);
        $dispatcher = $this->makeDispatcher(['Listener1' => $listener]);
        $dispatcher->listen($event::class, 'Listener1');

        $listener
            ->expects($this->never())
            ->method('handle');

        $dispatcher->dispatch($event);
    }

    #[Test]
    public function it_flushes_deferred_events(): void
    {
        $sequence = [];
        $event1 = new class () extends TestDomainEvent {};
        $event2 = $this->createStub(DomainEvent::class);

        $listener1 = $this->createMock(TestListener::class);
        $listener2 = $this->createMock(TestListener::class);
        $listener3 = $this->createMock(TestListener::class);
        $listener4 = $this->createMock(TestListener::class);
        $listener2Closure = static fn (DomainEvent $event) => $listener2->handle($event);

        $dispatcher = $this->makeDispatcher([
            'Listener1' => $listener1,
            'Listener3' => $listener3,
            'Listener4' => $listener4,
        ]);
        $dispatcher->listen($event1::class, ['Listener1', $listener2Closure, 'Listener3']);
        $dispatcher->listen($event2::class, 'Listener4');
        $dispatcher->dispatch($event1);
        $dispatcher->dispatch($event2);

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
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event1))
            ->willReturnCallback(function () use (&$sequence) {
                $sequence[] = 'Listener3';
            });

        $listener4
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event2))
            ->willReturnCallback(function () use (&$sequence) {
                $sequence[] = 'Listener4';
            });

        $dispatcher->flush();

        $this->assertSame(['Listener1', 'Listener2', 'Listener3', 'Listener4'], $sequence);
    }

    #[Test]
    public function it_flushes_deferred_events_including_events_dispatched_by_listeners(): void
    {
        $sequence = [];
        $event1 = new class () extends TestDomainEvent {};
        $event2 = $this->createStub(DomainEvent::class);

        $listener1 = $this->createMock(TestListener::class);
        $listener2 = $this->createMock(TestListener::class);
        $listener3 = $this->createMock(TestListener::class);

        $dispatcher = $this->makeDispatcher([
            'Listener1' => $listener1,
            'Listener2' => $listener2,
            'Listener3' => $listener3,
        ]);
        $dispatcher->listen($event1::class, ['Listener1', 'Listener2']);
        $dispatcher->listen($event2::class, 'Listener3');
        $dispatcher->dispatch($event1);

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
            ->willReturnCallback(function () use ($dispatcher, $event2, &$sequence) {
                $sequence[] = 'Listener2';
                $dispatcher->dispatch($event2);
            });

        $listener3
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event2))
            ->willReturnCallback(function () use (&$sequence) {
                $sequence[] = 'Listener3';
            });

        $dispatcher->flush();

        $this->assertSame(['Listener1', 'Listener2', 'Listener3'], $sequence);
    }

    #[Test]
    public function it_forgets_deferred_events(): void
    {
        $event = new class () extends TestDomainEvent {};
        $listener = $this->createMock(TestListener::class);
        $dispatcher = $this->makeDispatcher(['Listener1' => $listener]);
        $dispatcher->listen($event::class, 'Listener1');
        $dispatcher->dispatch($event);
        $dispatcher->forget();

        $listener
            ->expects($this->never())
            ->method('handle');

        $dispatcher->flush();
    }

    #[Test]
    public function it_throws_listener_exceptions_when_flushing(): void
    {
        $event = new class () extends TestDomainEvent {};
        $listener = $this->createStub(TestListener::class);
        $listener->method('handle')->willThrowException(new LogicException('Boom!'));
        $dispatcher = $this->makeDispatcher(['Listener1' => $listener]);
        $dispatcher->listen($event::class, 'Listener1');
        $dispatcher->dispatch($event);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Boom!');

        $dispatcher->flush();
    }

    #[Test]
    public function it_forgets_deferred_events_after_exception(): void
    {
        $event1 = new class () extends TestDomainEvent {};
        $event2 = $this->createStub(DomainEvent::class);

        $listener1 = $this->createMock(TestListener::class);
        $listener2 = $this->createMock(TestListener::class);
        $listener3 = $this->createMock(TestListener::class);
        $listener4 = $this->createMock(TestListener::class);

        $dispatcher = $this->makeDispatcher([
            'Listener1' => $listener1,
            'Listener2' => $listener2,
            'Listener3' => $listener3,
            'Listener4' => $listener4,
        ]);
        $dispatcher->listen($event1::class, ['Listener1', 'Listener2']);
        $dispatcher->listen($event2::class, ['Listener3', 'Listener4']);
        $dispatcher->dispatch($event1);
        $dispatcher->dispatch($event2);

        $listener1
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event1));

        $listener2
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event1))
            ->willThrowException(new LogicException('Boom!'));

        $listener3
            ->expects($this->never())
            ->method('handle');

        $listener4
            ->expects($this->never())
            ->method('handle');

        try {
            $dispatcher->flush();
        } catch (LogicException) {
            // expected
        }

        $dispatcher->flush();
    }

    #[Test]
    public function it_does_nothing_when_there_are_no_listeners(): void
    {
        $event = $this->createStub(DomainEvent::class);
        $listeners = $this->createMock(IListenerContainer::class);
        $dispatcher = new class ($listeners) extends DeferredDispatcher {};
        $dispatcher->dispatch($event);

        $listeners
            ->expects($this->never())
            ->method($this->anything());

        $dispatcher->flush();
    }

    #[Test]
    public function it_dispatches_through_middleware(): void
    {
        $event1 = new TestImmediateDomainEvent();
        $event2 = new TestImmediateDomainEvent();
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
        $middleware = new PsrPipeContainer();
        $middleware->bind('Middleware2', fn () => $b);

        $dispatcher = $this->makeDispatcher(['MyListener' => $listener], $middleware);
        $dispatcher->through([$a, 'Middleware2']);
        $dispatcher->listen($event3::class, 'MyListener');

        $listener
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event3));

        $dispatcher->dispatch($event1);
    }

    /**
     * @param array<non-empty-string, object> $listeners
     */
    private function makeDispatcher(array $listeners = [], ?PipeContainer $middleware = null): DeferredDispatcher
    {
        $container = new ListenerContainer();

        foreach ($listeners as $name => $listener) {
            $container->bind($name, fn () => $listener);
        }

        return new class ($container, $middleware) extends DeferredDispatcher {};
    }
}
