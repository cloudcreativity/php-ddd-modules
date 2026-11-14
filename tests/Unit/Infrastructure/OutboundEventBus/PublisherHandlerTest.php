<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Infrastructure\OutboundEventBus;

use CloudCreativity\Modules\Contracts\Infrastructure\OutboundEventBus\PublisherHandler as IPublisherHandler;
use CloudCreativity\Modules\Infrastructure\OutboundEventBus\PublisherHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PublisherHandlerTest extends TestCase
{
    #[Test]
    public function it_is_a_publisher_handler(): void
    {
        $handler = new PublisherHandler(new class () extends TestPublisher {});

        $this->assertInstanceOf(IPublisherHandler::class, $handler);
    }

    #[Test]
    public function it_publishes_the_event_via_the_inner_publisher(): void
    {
        $event = new class () extends TestOutboundEvent {};
        $innerHandler = new class () extends TestPublisher {
            /**
             * @var list<TestOutboundEvent>
             */
            public array $published = [];

            public function publish(TestOutboundEvent $event): void
            {
                $this->published[] = $event;
            }
        };
        $handler = new PublisherHandler($innerHandler);

        $handler($event);

        $this->assertSame([$event], $innerHandler->published);
    }
}
