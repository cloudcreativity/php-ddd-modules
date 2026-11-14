<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Testing;

use CloudCreativity\Modules\Contracts\Domain\Events\DomainEvent;
use CloudCreativity\Modules\Contracts\Domain\Events\DomainEventDispatcher;
use CloudCreativity\Modules\Testing\FakeDomainEventDispatcher;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FakeDomainEventDispatcherTest extends TestCase
{
    private FakeDomainEventDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new class () extends FakeDomainEventDispatcher {};
    }

    protected function tearDown(): void
    {
        unset($this->dispatcher);
        parent::tearDown();
    }

    #[Test]
    public function it_is_a_domain_event_dispatcher(): void
    {
        $this->assertInstanceOf(DomainEventDispatcher::class, $this->dispatcher);
    }

    #[Test]
    public function it_is_empty_by_default(): void
    {
        $actual = [count($this->dispatcher), iterator_to_array($this->dispatcher)];

        $this->assertSame([0, []], $actual);
    }

    // -------------------------------------------------------------------------
    // dispatch()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_dispatches_events(): void
    {
        $event1 = $this->createStub(DomainEvent::class);
        $event2 = $this->createStub(DomainEvent::class);

        $this->dispatcher->dispatch($event1);
        $this->dispatcher->dispatch($event2);

        $this->assertSame([$event1, $event2], $this->dispatcher->events);
    }

    // -------------------------------------------------------------------------
    // count() and getIterator()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_counts_the_dispatched_events(): void
    {
        $this->dispatcher->events = [
            $this->createStub(DomainEvent::class),
            $this->createStub(DomainEvent::class),
        ];

        $actual = count($this->dispatcher);

        $this->assertSame(2, $actual);
    }

    #[Test]
    public function it_iterates_over_the_dispatched_events(): void
    {
        $event1 = $this->createStub(DomainEvent::class);
        $event2 = $this->createStub(DomainEvent::class);
        $this->dispatcher->events = [$event1, $event2];

        $actual = iterator_to_array($this->dispatcher);

        $this->assertSame([$event1, $event2], $actual);
    }

    // -------------------------------------------------------------------------
    // ArrayAccess
    // -------------------------------------------------------------------------

    #[Test]
    public function it_gets_an_event_by_offset(): void
    {
        $event1 = $this->createStub(DomainEvent::class);
        $event2 = $this->createStub(DomainEvent::class);
        $this->dispatcher->events = [$event1, $event2];

        $actual = [$this->dispatcher[0], $this->dispatcher[1]];

        $this->assertSame([$event1, $event2], $actual);
    }

    #[Test]
    public function it_checks_whether_an_offset_exists(): void
    {
        $this->dispatcher->events = [$this->createStub(DomainEvent::class)];

        $actual = [isset($this->dispatcher[0]), isset($this->dispatcher[1])];

        $this->assertSame([true, false], $actual);
    }

    #[Test]
    public function it_cannot_set_an_offset(): void
    {
        $event = $this->createStub(DomainEvent::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot set domain events.');

        $this->dispatcher[0] = $event;
    }

    #[Test]
    public function it_cannot_unset_an_offset(): void
    {
        $this->dispatcher->events = [$this->createStub(DomainEvent::class)];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot unset domain events.');

        unset($this->dispatcher[0]);
    }

    // -------------------------------------------------------------------------
    // sole()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_sole_event(): void
    {
        $event = $this->createStub(DomainEvent::class);
        $this->dispatcher->events = [$event];

        $actual = $this->dispatcher->sole();

        $this->assertSame($event, $actual);
    }

    #[Test]
    public function it_throws_exception_if_no_events(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected one event to be dispatched but there are 0 events.');

        $this->dispatcher->sole();
    }

    #[Test]
    public function it_throws_exception_if_multiple_events(): void
    {
        $this->dispatcher->events = [
            $this->createStub(DomainEvent::class),
            $this->createStub(DomainEvent::class),
        ];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected one event to be dispatched but there are 2 events.');

        $this->dispatcher->sole();
    }
}
