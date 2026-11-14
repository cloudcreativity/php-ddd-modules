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

use CloudCreativity\Modules\Bus\SwallowInboundEvent;
use CloudCreativity\Modules\Testing\FakeLogger;
use CloudCreativity\Modules\Toolkit\ModuleBasename;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class SwallowInboundEventTest extends TestCase
{
    #[Test]
    public function it_does_nothing(): void
    {
        $handler = new SwallowInboundEvent();

        $this->expectNotToPerformAssertions();

        $handler->handle(new TestInboundEvent());
    }

    #[Test]
    public function it_logs_that_it_does_nothing(): void
    {
        $event = new TestInboundEvent();
        $name = ModuleBasename::from($event);
        $logger = new FakeLogger();
        $handler = new SwallowInboundEvent($logger);

        $handler->handle($event);

        $this->assertSame([
            ['level' => LogLevel::DEBUG, 'message' => "Swallowing inbound integration event {$name}.", 'context' => []],
        ], $logger->log);
    }

    #[Test]
    public function it_logs_that_it_does_nothing_with_specified_log_level(): void
    {
        $event = new TestInboundEvent();
        $name = ModuleBasename::from($event);
        $logger = new FakeLogger();
        $handler = new SwallowInboundEvent(logger: $logger, level: LogLevel::NOTICE);

        $handler->handle($event);

        $this->assertSame([
            ['level' => LogLevel::NOTICE, 'message' => "Swallowing inbound integration event {$name}.", 'context' => []],
        ], $logger->log);
    }
}
