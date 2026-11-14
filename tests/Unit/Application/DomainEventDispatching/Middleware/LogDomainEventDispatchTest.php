<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\DomainEventDispatching\Middleware;

use CloudCreativity\Modules\Application\DomainEventDispatching\Middleware\LogDomainEventDispatch;
use CloudCreativity\Modules\Contracts\Domain\Events\DomainEvent;
use CloudCreativity\Modules\Testing\FakeLogger;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class LogDomainEventDispatchTest extends TestCase
{
    private FakeLogger $logger;

    private DomainEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new FakeLogger();

        /**
         * Do not expect any public properties to be logged as context.
         * This is because the event is in the domain layer, so cannot implement the
         * log context provider interface to override any context logging. That means
         * if we automatically logged context there would be no way for the developer
         * to prevent sensitive properties from being logged.
         */
        $this->event = new class () implements DomainEvent {
            public string $foo = 'foo';
            public string $bar = 'bar';

            public function getOccurredAt(): DateTimeImmutable
            {
                return new DateTimeImmutable();
            }
        };
    }

    protected function tearDown(): void
    {
        unset($this->logger, $this->event);
        parent::tearDown();
    }

    #[Test]
    public function it_logs_with_the_default_levels(): void
    {
        $eventName = $this->event::class;
        $middleware = new LogDomainEventDispatch($this->logger);

        $middleware($this->event, function (DomainEvent $received) {
            $this->assertSame($this->event, $received);
        });

        $this->assertSame([
            ['level' => LogLevel::DEBUG, 'message' => "Dispatching domain event {$eventName}.", 'context' => []],
            ['level' => LogLevel::INFO, 'message' => "Dispatched domain event {$eventName}.", 'context' => []],
        ], $this->logger->log);
    }

    #[Test]
    public function it_logs_with_custom_levels(): void
    {
        $eventName = $this->event::class;
        $middleware = new LogDomainEventDispatch($this->logger, LogLevel::NOTICE, LogLevel::WARNING);

        $middleware($this->event, function (DomainEvent $received) {
            $this->assertSame($this->event, $received);
        });

        $this->assertSame([
            ['level' => LogLevel::NOTICE, 'message' => "Dispatching domain event {$eventName}.", 'context' => []],
            ['level' => LogLevel::WARNING, 'message' => "Dispatched domain event {$eventName}.", 'context' => []],
        ], $this->logger->log);
    }

    #[Test]
    public function it_logs_after_the_next_closure_is_invoked(): void
    {
        $expected = new LogicException();
        $eventName = $this->event::class;
        $middleware = new LogDomainEventDispatch($this->logger);
        $actual = null;

        try {
            $middleware($this->event, static function () use ($expected) {
                throw $expected;
            });
        } catch (LogicException $ex) {
            $actual = $ex;
        }

        $this->assertSame($expected, $actual);
        $this->assertSame([
            ['level' => LogLevel::DEBUG, 'message' => "Dispatching domain event {$eventName}.", 'context' => []],
        ], $this->logger->log);
    }
}
