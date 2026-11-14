<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Integration\Infrastructure\OutboundEventBus;

use CloudCreativity\Modules\Infrastructure\OutboundEventBus\Middleware\LogOutboundEvent;
use CloudCreativity\Modules\Testing\FakeContainer;
use CloudCreativity\Modules\Tests\Integration\Bus\NumbersAdded;
use CloudCreativity\Modules\Tests\Integration\Bus\NumbersDivided;
use CloudCreativity\Modules\Tests\Integration\Bus\NumbersSubtracted;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MathEventPublisherTest extends TestCase
{
    private FakeContainer $container;

    private NumbersAddedPublisher $addedPublisher;

    private NumbersSubtractedPublisher $subtractedPublisher;

    private TestDefaultPublisher $defaultPublisher;

    private MathEventPublisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addedPublisher = $a = new NumbersAddedPublisher();
        $this->subtractedPublisher = $b = new NumbersSubtractedPublisher();
        $this->defaultPublisher = $c = new TestDefaultPublisher();

        $this->container = $container = new FakeContainer();
        $container->bind(NumbersAddedPublisher::class, fn () => $a);
        $container->bind(NumbersSubtractedPublisher::class, fn () => $b);
        $container->bind(TestDefaultPublisher::class, fn () => $c);
        $container->bind(LogOutboundEvent::class, fn () => new LogOutboundEvent($container->logger));

        $this->publisher = new MathEventPublisher($container);
    }

    protected function tearDown(): void
    {
        unset(
            $this->container,
            $this->addedPublisher,
            $this->subtractedPublisher,
            $this->defaultPublisher,
            $this->publisher,
        );
        parent::tearDown();
    }

    #[Test]
    public function it_publishes_an_event_via_its_publisher(): void
    {
        $event = new NumbersAdded(1, 2, 3);

        $this->publisher->publish($event);

        $this->assertSame([$event], $this->addedPublisher->published);
        $this->assertSame([], $this->subtractedPublisher->published);
        $this->assertSame([], $this->defaultPublisher->published);
        $this->assertCount(2, $this->container->logger);
    }

    #[Test]
    public function it_publishes_another_event_via_its_publisher(): void
    {
        $event = new NumbersSubtracted(10, 6, 4);

        $this->publisher->publish($event);

        $this->assertSame([], $this->addedPublisher->published);
        $this->assertSame([$event], $this->subtractedPublisher->published);
        $this->assertSame([], $this->defaultPublisher->published);
        $this->assertCount(2, $this->container->logger);
    }

    #[Test]
    public function it_publishes_an_event_via_the_default_publisher(): void
    {
        $event = new NumbersDivided(12, 3, 4);

        $this->publisher->publish($event);

        $this->assertSame([], $this->addedPublisher->published);
        $this->assertSame([], $this->subtractedPublisher->published);
        $this->assertSame([$event], $this->defaultPublisher->published);
        $this->assertCount(2, $this->container->logger);
    }
}
