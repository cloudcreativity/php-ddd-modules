<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Infrastructure\OutboundEventBus\Middleware;

use CloudCreativity\Modules\Contracts\Messaging\IntegrationEvent;
use CloudCreativity\Modules\Infrastructure\OutboundEventBus\Middleware\LogOutboundEvent;
use CloudCreativity\Modules\Testing\FakeLogger;
use CloudCreativity\Modules\Tests\Unit\Infrastructure\OutboundEventBus\TestNamedOutboundEvent;
use CloudCreativity\Modules\Tests\Unit\Infrastructure\OutboundEventBus\TestOutboundEvent;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class LogOutboundEventTest extends TestCase
{
    private FakeLogger $logger;

    private TestNamedOutboundEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new FakeLogger();
        $this->event = new TestNamedOutboundEvent(name: 'bar');
    }

    protected function tearDown(): void
    {
        unset($this->logger, $this->event);
        parent::tearDown();
    }

    #[Test]
    public function it_logs_with_the_default_levels(): void
    {
        $passed = null;
        $middleware = new LogOutboundEvent($this->logger);

        $middleware($this->event, function (IntegrationEvent $event) use (&$passed): void {
            $passed = $event;
        });

        $context = ['event' => [
            'name' => 'bar',
            'uuid' => $this->event->uuid,
            'occurredAt' => $this->event->occurredAt,
        ]];

        $this->assertSame($this->event, $passed);
        $this->assertEquals([
            ['level' => LogLevel::DEBUG, 'message' => 'Publishing integration event Tests:TestNamedOutboundEvent.', 'context' => $context],
            ['level' => LogLevel::INFO, 'message' => 'Published integration event Tests:TestNamedOutboundEvent.', 'context' => $context],
        ], $this->logger->log);
    }

    #[Test]
    public function it_logs_with_custom_levels(): void
    {
        $middleware = new LogOutboundEvent($this->logger, LogLevel::NOTICE, LogLevel::WARNING);

        $middleware($this->event, function (): void {
        });

        $this->assertSame([
            'NOTICE: Publishing integration event Tests:TestNamedOutboundEvent.',
            'WARNING: Published integration event Tests:TestNamedOutboundEvent.',
        ], $this->logger->messages());
    }

    #[Test]
    public function it_uses_the_fully_qualified_class_name_when_there_is_no_module_basename(): void
    {
        $event = new class () extends TestOutboundEvent {};
        $name = $event::class;
        $middleware = new LogOutboundEvent($this->logger);

        $middleware($event, function (): void {
        });

        $this->assertSame([
            "DEBUG: Publishing integration event {$name}.",
            "INFO: Published integration event {$name}.",
        ], $this->logger->messages());
    }

    #[Test]
    public function it_logs_before_the_next_closure_is_invoked(): void
    {
        $messages = null;
        $middleware = new LogOutboundEvent($this->logger);

        $middleware($this->event, function () use (&$messages): void {
            $messages = $this->logger->messages();
        });

        $this->assertSame(['DEBUG: Publishing integration event Tests:TestNamedOutboundEvent.'], $messages);
    }

    #[Test]
    public function it_rethrows_an_exception_thrown_by_the_next_closure(): void
    {
        $middleware = new LogOutboundEvent($this->logger);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Boom!');

        $middleware($this->event, static function (): never {
            throw new LogicException('Boom!');
        });
    }
}
