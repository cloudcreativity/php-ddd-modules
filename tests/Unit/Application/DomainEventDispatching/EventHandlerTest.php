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

use CloudCreativity\Modules\Application\DomainEventDispatching\EventHandler;
use CloudCreativity\Modules\Contracts\Domain\Events\DomainEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventHandlerTest extends TestCase
{
    #[Test]
    public function it_casts_to_the_listener_class_name(): void
    {
        $listener = new class () extends TestListener {};
        $handler = new EventHandler($listener);

        $actual = (string) $handler;

        $this->assertSame($listener::class, $actual);
    }

    #[Test]
    public function it_is_not_dispatched_before_or_after_commit_by_default(): void
    {
        $handler = new EventHandler(new class () extends TestListener {});

        $this->assertFalse($handler->beforeCommit());
        $this->assertFalse($handler->afterCommit());
    }

    #[Test]
    public function it_is_dispatched_before_commit(): void
    {
        $handler = new EventHandler(new class () extends TestListenerBeforeCommit {});

        $this->assertTrue($handler->beforeCommit());
        $this->assertFalse($handler->afterCommit());
    }

    #[Test]
    public function it_is_dispatched_after_commit(): void
    {
        $handler = new EventHandler(new class () extends TestListenerAfterCommit {});

        $this->assertFalse($handler->beforeCommit());
        $this->assertTrue($handler->afterCommit());
    }

    #[Test]
    public function it_invokes_the_listener_handle_method(): void
    {
        $event = new TestImmediateDomainEvent();
        $listener = $this->createMock(TestListener::class);
        $handler = new EventHandler($listener);

        $listener
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event));

        $handler($event);
    }

    #[Test]
    public function it_invokes_a_closure_listener(): void
    {
        $event = new TestImmediateDomainEvent();
        $received = null;
        $handler = new EventHandler(function (DomainEvent $actual) use (&$received): void {
            $received = $actual;
        });

        $handler($event);

        $this->assertSame($event, $received);
    }
}
