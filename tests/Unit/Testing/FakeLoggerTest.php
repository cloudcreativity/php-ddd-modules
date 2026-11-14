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

use CloudCreativity\Modules\Testing\FakeLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use stdClass;
use Stringable;

final class FakeLoggerTest extends TestCase
{
    private FakeLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new FakeLogger();
    }

    protected function tearDown(): void
    {
        unset($this->logger);
        parent::tearDown();
    }

    #[Test]
    public function it_is_a_psr_logger(): void
    {
        $this->assertInstanceOf(LoggerInterface::class, $this->logger);
    }

    #[Test]
    public function it_is_empty_by_default(): void
    {
        $actual = [count($this->logger), $this->logger->log, $this->logger->messages()];

        $this->assertSame([0, [], []], $actual);
    }

    // -------------------------------------------------------------------------
    // log()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_records_log_entries(): void
    {
        $this->logger->log(LogLevel::INFO, 'First message', ['foo' => 'bar']);
        $this->logger->debug('Second message');

        $this->assertSame([
            ['level' => LogLevel::INFO, 'message' => 'First message', 'context' => ['foo' => 'bar']],
            ['level' => LogLevel::DEBUG, 'message' => 'Second message', 'context' => []],
        ], $this->logger->log);
    }

    #[Test]
    public function it_counts_the_log_entries(): void
    {
        $this->logger->log = [
            ['level' => LogLevel::INFO, 'message' => 'First message', 'context' => []],
            ['level' => LogLevel::DEBUG, 'message' => 'Second message', 'context' => []],
        ];

        $actual = count($this->logger);

        $this->assertSame(2, $actual);
    }

    #[Test]
    public function it_casts_a_stringable_message_to_a_string(): void
    {
        $message = new class () implements Stringable {
            public function __toString(): string
            {
                return 'Stringable message';
            }
        };

        $this->logger->warning($message);

        $this->assertSame([
            ['level' => LogLevel::WARNING, 'message' => 'Stringable message', 'context' => []],
        ], $this->logger->log);
    }

    // -------------------------------------------------------------------------
    // messages()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_messages_prefixed_with_the_level(): void
    {
        $this->logger->log = [
            ['level' => LogLevel::INFO, 'message' => 'First message', 'context' => ['foo' => 'bar']],
            ['level' => LogLevel::ERROR, 'message' => 'Second message', 'context' => []],
        ];

        $actual = $this->logger->messages();

        $this->assertSame(['INFO: First message', 'ERROR: Second message'], $actual);
    }

    #[Test]
    public function it_returns_messages_without_a_prefix_when_the_level_is_not_scalar(): void
    {
        $this->logger->log = [
            ['level' => new stdClass(), 'message' => 'Some message', 'context' => []],
        ];

        $actual = $this->logger->messages();

        $this->assertSame(['Some message'], $actual);
    }
}
