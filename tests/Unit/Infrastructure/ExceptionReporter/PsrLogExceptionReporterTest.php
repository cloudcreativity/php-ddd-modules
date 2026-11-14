<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Infrastructure\ExceptionReporter;

use CloudCreativity\Modules\Contracts\Application\Ports\ExceptionReporter;
use CloudCreativity\Modules\Infrastructure\ExceptionReporter\PsrLogExceptionReporter;
use CloudCreativity\Modules\Testing\FakeLogger;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class PsrLogExceptionReporterTest extends TestCase
{
    private FakeLogger $logger;

    private PsrLogExceptionReporter $reporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new FakeLogger();
        $this->reporter = new PsrLogExceptionReporter($this->logger);
    }

    protected function tearDown(): void
    {
        unset($this->logger, $this->reporter);
        parent::tearDown();
    }

    #[Test]
    public function it_is_an_exception_reporter(): void
    {
        $this->assertInstanceOf(ExceptionReporter::class, $this->reporter);
    }

    #[Test]
    public function it_reports_exception(): void
    {
        $exception = new LogicException('Test exception');

        $this->reporter->report($exception);

        $this->assertSame([
            ['level' => LogLevel::ERROR, 'message' => 'Test exception', 'context' => ['exception' => $exception]],
        ], $this->logger->log);
    }

    #[Test]
    public function it_reports_default_message_if_exception_has_empty_message(): void
    {
        $exception = new LogicException();

        $this->reporter->report($exception);

        $this->assertSame([
            [
                'level' => LogLevel::ERROR,
                'message' => 'Unexpected error: LogicException',
                'context' => ['exception' => $exception],
            ],
        ], $this->logger->log);
    }

    #[Test]
    public function it_logs_context_for_exception_that_has_context_method(): void
    {
        $exception = new class ('Boom!') extends LogicException {
            /**
             * @return array<string, mixed>
             */
            public function context(): array
            {
                return ['foo' => 'bar', 'exception' => null];
            }
        };

        $this->reporter->report($exception);

        $this->assertSame([
            [
                'level' => LogLevel::ERROR,
                'message' => 'Boom!',
                'context' => ['foo' => 'bar', 'exception' => $exception],
            ],
        ], $this->logger->log);
    }

    #[Test]
    public function it_ignores_context_that_is_not_an_array(): void
    {
        $exception = new class ('Boom!') extends LogicException {
            public function context(): string
            {
                return 'not an array';
            }
        };

        $this->reporter->report($exception);

        $this->assertSame([
            ['level' => LogLevel::ERROR, 'message' => 'Boom!', 'context' => ['exception' => $exception]],
        ], $this->logger->log);
    }
}
