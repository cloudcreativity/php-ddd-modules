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

use CloudCreativity\Modules\Contracts\Application\Ports\OutboundEventPublisher;
use CloudCreativity\Modules\Contracts\Messaging\IntegrationEvent;
use CloudCreativity\Modules\Testing\FakeOutboundEventPublisher;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FakeOutboundEventPublisherTest extends TestCase
{
    private FakeOutboundEventPublisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publisher = new class () extends FakeOutboundEventPublisher {};
    }

    protected function tearDown(): void
    {
        unset($this->publisher);
        parent::tearDown();
    }

    #[Test]
    public function it_is_an_outbound_event_publisher(): void
    {
        $this->assertInstanceOf(OutboundEventPublisher::class, $this->publisher);
    }

    #[Test]
    public function it_is_empty_by_default(): void
    {
        $actual = [count($this->publisher), iterator_to_array($this->publisher)];

        $this->assertSame([0, []], $actual);
    }

    // -------------------------------------------------------------------------
    // publish()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_publishes_events(): void
    {
        $event1 = $this->createStub(IntegrationEvent::class);
        $event2 = $this->createStub(IntegrationEvent::class);

        $this->publisher->publish($event1);
        $this->publisher->publish($event2);

        $this->assertSame([$event1, $event2], $this->publisher->events);
    }

    // -------------------------------------------------------------------------
    // count() and getIterator()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_counts_the_published_events(): void
    {
        $this->publisher->events = [
            $this->createStub(IntegrationEvent::class),
            $this->createStub(IntegrationEvent::class),
        ];

        $actual = count($this->publisher);

        $this->assertSame(2, $actual);
    }

    #[Test]
    public function it_iterates_over_the_published_events(): void
    {
        $event1 = $this->createStub(IntegrationEvent::class);
        $event2 = $this->createStub(IntegrationEvent::class);
        $this->publisher->events = [$event1, $event2];

        $actual = iterator_to_array($this->publisher);

        $this->assertSame([$event1, $event2], $actual);
    }

    // -------------------------------------------------------------------------
    // ArrayAccess
    // -------------------------------------------------------------------------

    #[Test]
    public function it_gets_an_event_by_offset(): void
    {
        $event1 = $this->createStub(IntegrationEvent::class);
        $event2 = $this->createStub(IntegrationEvent::class);
        $this->publisher->events = [$event1, $event2];

        $actual = [$this->publisher[0], $this->publisher[1]];

        $this->assertSame([$event1, $event2], $actual);
    }

    #[Test]
    public function it_checks_whether_an_offset_exists(): void
    {
        $this->publisher->events = [$this->createStub(IntegrationEvent::class)];

        $actual = [isset($this->publisher[0]), isset($this->publisher[1])];

        $this->assertSame([true, false], $actual);
    }

    #[Test]
    public function it_cannot_set_an_offset(): void
    {
        $event = $this->createStub(IntegrationEvent::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot set integration events.');

        $this->publisher[0] = $event;
    }

    #[Test]
    public function it_cannot_unset_an_offset(): void
    {
        $this->publisher->events = [$this->createStub(IntegrationEvent::class)];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot unset integration events.');

        unset($this->publisher[0]);
    }

    // -------------------------------------------------------------------------
    // sole()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_sole_event(): void
    {
        $event = $this->createStub(IntegrationEvent::class);
        $this->publisher->events = [$event];

        $actual = $this->publisher->sole();

        $this->assertSame($event, $actual);
    }

    #[Test]
    public function it_throws_exception_if_no_events(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected one event to be published but there are 0 events.');

        $this->publisher->sole();
    }

    #[Test]
    public function it_throws_exception_if_multiple_events(): void
    {
        $this->publisher->events = [
            $this->createStub(IntegrationEvent::class),
            $this->createStub(IntegrationEvent::class),
        ];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected one event to be published but there are 2 events.');

        $this->publisher->sole();
    }
}
