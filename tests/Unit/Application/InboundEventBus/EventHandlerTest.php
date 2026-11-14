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

use CloudCreativity\Modules\Bus\EventHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventHandlerTest extends TestCase
{
    #[Test]
    public function it_handles_the_event(): void
    {
        $event = new TestInboundEvent();
        $innerHandler = $this->createMock(TestEventHandler::class);
        $handler = new EventHandler($innerHandler);

        $innerHandler
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($event));

        $handler($event);
    }

    #[Test]
    public function it_returns_the_middleware_of_the_inner_handler(): void
    {
        $innerHandler = $this->createStub(TestEventHandler::class);
        $innerHandler->method('middleware')->willReturn(['Middleware1', 'Middleware2']);
        $handler = new EventHandler($innerHandler);

        $actual = $handler->middleware();

        $this->assertSame(['Middleware1', 'Middleware2'], $actual);
    }

    #[Test]
    public function it_returns_middleware_from_the_through_attribute(): void
    {
        $handler = new EventHandler(new TestEventHandlerWithThrough());

        $actual = $handler->middleware();

        $this->assertSame(['Middleware1', 'Middleware2'], $actual);
    }

    #[Test]
    public function it_returns_no_middleware_when_none_is_declared(): void
    {
        $handler = new EventHandler(new \stdClass());

        $actual = $handler->middleware();

        $this->assertSame([], $actual);
    }
}
