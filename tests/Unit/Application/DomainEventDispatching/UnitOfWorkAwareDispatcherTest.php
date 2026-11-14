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
use CloudCreativity\Modules\Application\DomainEventDispatching\ListenerContainer;
use CloudCreativity\Modules\Application\DomainEventDispatching\UnitOfWorkAwareDispatcher;
use CloudCreativity\Modules\Application\UnitOfWork\UnitOfWorkManager as UnitOfWorkManagerImpl;
use CloudCreativity\Modules\Bus\PsrPipeContainer;
use CloudCreativity\Modules\Contracts\Application\DomainEventDispatching\ListenerContainer as IListenerContainer;
use CloudCreativity\Modules\Contracts\Application\UnitOfWork\UnitOfWorkManager;
use CloudCreativity\Modules\Contracts\Domain\Events\DomainEvent;
use CloudCreativity\Modules\Contracts\Domain\Events\DomainEventDispatcher;
use CloudCreativity\Modules\Contracts\Toolkit\Pipeline\PipeContainer;
use CloudCreativity\Modules\Testing\FakeLogger;
use CloudCreativity\Modules\Testing\FakeUnitOfWork;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class UnitOfWorkAwareDispatcherTest extends TestCase
{
    #[Test]
    public function it_is_a_domain_event_dispatcher(): void
    {
        $dispatcher = $this->makeDispatcher($this->createStub(UnitOfWorkManager::class));

        $this->assertInstanceOf(DomainEventDispatcher::class, $dispatcher);
    }

    #[Test]
    public function it_dispatches_immediately(): void
    {
        $sequence = [];
        $event = new TestImmediateDomainEvent();
        $unitOfWorkManager = $this->createMock(UnitOfWorkManager::class);

        $listener1 = $this->createMock(TestListener::class);
        $listener2 = $this->createMock(TestListener::class);
        $listener3 = $this->createMock(TestListener::class);
        $listener4 = $this->createMock(TestListenerBeforeCommit::class);
        $listener5 = $this->createMock(TestListenerAfterCommit::class);
        $listener6 = $this->createMock(TestListenerAfterCommit::class);
        $listener7 = $this->createMock(TestListener::class);
        $listener2Closure = static fn (DomainEvent $event) => $listener2->handle($event);

        $dispatcher = $this->makeDispatcher($unitOfWorkManager, [
            'Listener1' => $listener1,
            'Listener3' => $listener3,
            'Listener4' => $listener4,
            'Listener5' => $listener5,
            'Listener6' => $listener6,
            'Listener7' => $listener7,
        ]);
        $dispatcher->listen($event::class, [
            'Listener1',
            $listener2Closure,
            'Listener3',
            'Listener4',
            'Listener5',
            'Listener6',
        ]);
        $dispatcher->listen(TestDomainEvent::class, 'Listener7');

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

        $listener5
            ->expects($this->never())
            ->method('handle');

        $listener6
            ->expects($this->never())
            ->method('handle');

        $listener7
            ->expects($this->never())
            ->method('handle');

        $unitOfWorkManager
            ->expects($this->once())
            ->method('beforeCommit');

        $unitOfWorkManager
            ->expects($this->exactly(2))
            ->method('afterCommit');

        $dispatcher->dispatch($event);

        $this->assertSame(['Listener1', 'Listener2', 'Listener3'], $sequence);
    }

    #[Test]
    public function it_does_not_dispatch_immediately(): void
    {
        $event = $this->createStub(DomainEvent::class);
        $unitOfWorkManager = $this->createMock(UnitOfWorkManager::class);
        $listener1 = $this->createMock(TestListener::class);
        $listener2 = $this->createMock(TestListener::class);

        $dispatcher = $this->makeDispatcher($unitOfWorkManager, [
            'Listener1' => $listener1,
            'Listener2' => $listener2,
        ]);
        $dispatcher->listen($event::class, ['Listener1', 'Listener2']);

        $listener1
            ->expects($this->never())
            ->method('handle');

        $listener2
            ->expects($this->never())
            ->method('handle');

        $unitOfWorkManager
            ->expects($this->once())
            ->method('beforeCommit');

        $unitOfWorkManager
            ->expects($this->never())
            ->method('afterCommit');

        $dispatcher->dispatch($event);
    }

    #[Test]
    public function it_dispatches_event_in_before_commit_callback(): void
    {
        $event = new class () extends TestDomainEvent {};
        $unitOfWorkManager = $this->createMock(UnitOfWorkManager::class);
        $listener1 = $this->createMock(TestListener::class);
        $listener2 = $this->createMock(TestListener::class);

        $dispatcher = $this->makeDispatcher($unitOfWorkManager, [
            'Listener1' => $listener1,
            'Listener2' => $listener2,
        ]);
        $dispatcher->listen($event::class, ['Listener1', 'Listener2']);

        $listener1
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event));

        $listener2
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event));

        $unitOfWorkManager
            ->expects($this->once())
            ->method('beforeCommit')
            ->willReturnCallback(function (Closure $callback): void {
                $callback();
            });

        $dispatcher->dispatch($event);
    }

    #[Test]
    public function it_dispatches_to_a_before_commit_listener_before_commit(): void
    {
        $event = new TestImmediateDomainEvent();
        $unitOfWorkManager = $this->createMock(UnitOfWorkManager::class);
        $listener = $this->createMock(TestListenerBeforeCommit::class);

        $dispatcher = $this->makeDispatcher($unitOfWorkManager, ['BeforeCommitListener' => $listener]);
        $dispatcher->listen($event::class, 'BeforeCommitListener');

        $listener
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event));

        $unitOfWorkManager
            ->expects($this->once())
            ->method('beforeCommit')
            ->willReturnCallback(function (Closure $callback): void {
                $callback();
            });

        $unitOfWorkManager
            ->expects($this->never())
            ->method('afterCommit');

        $dispatcher->dispatch($event);
    }

    #[Test]
    public function it_dispatches_to_an_after_commit_listener_after_commit(): void
    {
        $event = new TestImmediateDomainEvent();
        $unitOfWorkManager = $this->createMock(UnitOfWorkManager::class);
        $listener = $this->createMock(TestListenerAfterCommit::class);

        $dispatcher = $this->makeDispatcher($unitOfWorkManager, ['AfterCommitListener' => $listener]);
        $dispatcher->listen($event::class, 'AfterCommitListener');

        $listener
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event));

        $unitOfWorkManager
            ->expects($this->once())
            ->method('afterCommit')
            ->willReturnCallback(function (Closure $callback): void {
                $callback();
            });

        $unitOfWorkManager
            ->expects($this->never())
            ->method('beforeCommit');

        $dispatcher->dispatch($event);
    }

    #[Test]
    public function it_does_nothing_when_there_are_no_listeners(): void
    {
        $event = $this->createStub(DomainEvent::class);
        $listeners = $this->createMock(IListenerContainer::class);
        $dispatcher = new class (
            unitOfWorkManager: $this->createStub(UnitOfWorkManager::class),
            listeners: $listeners,
        ) extends UnitOfWorkAwareDispatcher {};

        $listeners
            ->expects($this->never())
            ->method($this->anything());

        $dispatcher->dispatch($event);
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

        $dispatcher = $this->makeDispatcher(
            $this->createStub(UnitOfWorkManager::class),
            ['MyListener' => $listener],
            $middleware,
        );
        $dispatcher->through([$a, 'Middleware2']);
        $dispatcher->listen($event3::class, 'MyListener');

        $listener
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event3));

        $dispatcher->dispatch($event1);
    }

    #[Test]
    public function it_logs_before_commit_listeners(): void
    {
        $event = new TestImmediateDomainEvent();
        $listener = $this->createMock(TestListenerBeforeCommit::class);
        $logger = new FakeLogger();
        $unitOfWork = new FakeUnitOfWork();
        $unitOfWorkManager = new UnitOfWorkManagerImpl($unitOfWork);
        $dispatcher = $this->makeDispatcher($unitOfWorkManager, ['MyListener' => $listener], logger: $logger);
        $dispatcher->listen($event::class, 'MyListener');
        $context = ['event' => $event::class, 'listener' => $listener::class];

        $listener
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event));

        $unitOfWorkManager->execute(static function () use ($dispatcher, $event): void {
            $dispatcher->dispatch($event);
        });

        $this->assertSame([
            ['level' => LogLevel::DEBUG, 'message' => 'Deferring listener to be handled before commit.', 'context' => $context],
            ['level' => LogLevel::DEBUG, 'message' => 'Executing listener before commit.', 'context' => $context],
        ], $logger->log);
        $this->assertSame(1, $unitOfWork->commits);
    }

    #[Test]
    public function it_logs_after_commit_listeners(): void
    {
        $event = new TestImmediateDomainEvent();
        $listener = $this->createMock(TestListenerAfterCommit::class);
        $logger = new FakeLogger();
        $unitOfWork = new FakeUnitOfWork();
        $unitOfWorkManager = new UnitOfWorkManagerImpl($unitOfWork);
        $dispatcher = $this->makeDispatcher($unitOfWorkManager, ['MyListener' => $listener], logger: $logger);
        $dispatcher->listen($event::class, 'MyListener');
        $context = ['event' => $event::class, 'listener' => $listener::class];

        $listener
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event));

        $unitOfWorkManager->execute(static function () use ($dispatcher, $event): void {
            $dispatcher->dispatch($event);
        });

        $this->assertSame([
            ['level' => LogLevel::DEBUG, 'message' => 'Deferring listener to be handled after commit.', 'context' => $context],
            ['level' => LogLevel::DEBUG, 'message' => 'Executing listener after commit.', 'context' => $context],
        ], $logger->log);
        $this->assertSame(1, $unitOfWork->commits);
    }

    /**
     * @param array<non-empty-string, object> $listeners
     */
    private function makeDispatcher(
        UnitOfWorkManager $unitOfWorkManager,
        array $listeners = [],
        ?PipeContainer $middleware = null,
        ?FakeLogger $logger = null,
    ): UnitOfWorkAwareDispatcher {
        $container = new ListenerContainer();

        foreach ($listeners as $name => $listener) {
            $container->bind($name, fn () => $listener);
        }

        return new class (
            unitOfWorkManager: $unitOfWorkManager,
            listeners: $container,
            middleware: $middleware,
            logger: $logger,
        ) extends UnitOfWorkAwareDispatcher {};
    }
}
