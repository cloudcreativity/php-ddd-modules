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
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PsrLogExceptionReporterTest extends TestCase
{
    private LoggerInterface&MockObject $logger;

    private PsrLogExceptionReporter $reporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->reporter = new PsrLogExceptionReporter($this->logger);
    }

    protected function tearDown(): void
    {
        unset($this->logger, $this->reporter);
        parent::tearDown();
    }

    #[Test]
    public function it_reports_exception(): void
    {
        $exception = new LogicException('Test exception');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                $exception->getMessage(),
                ['exception' => $exception],
            );

        $this->reporter->report($exception);

        $this->assertInstanceOf(ExceptionReporter::class, $this->reporter);
    }

    #[Test]
    public function it_reports_default_message_if_exception_has_empty_message(): void
    {
        $exception = new LogicException();

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected error: LogicException',
                ['exception' => $exception],
            );

        $this->reporter->report($exception);
    }

    #[Test]
    public function it_logs_context_for_exception_that_implements_context_provider(): void
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

        $expected = [
            'foo' => 'bar',
            'exception' => $exception,
        ];

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                $exception->getMessage(),
                $this->identicalTo($expected),
            );

        $this->reporter->report($exception);
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

        $expected = [
            'foo' => 'bar',
            'exception' => $exception,
        ];

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                $exception->getMessage(),
                $this->identicalTo($expected),
            );

        $this->reporter->report($exception);
    }
}
