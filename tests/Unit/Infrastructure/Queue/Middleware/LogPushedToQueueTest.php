<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Infrastructure\Queue\Middleware;

use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Infrastructure\Queue\Middleware\LogPushedToQueue;
use CloudCreativity\Modules\Testing\FakeLogger;
use CloudCreativity\Modules\Tests\Unit\Infrastructure\Queue\TestQueueableCommand;
use CloudCreativity\Modules\Toolkit\Result\Result;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class LogPushedToQueueTest extends TestCase
{
    private FakeLogger $logger;

    private TestQueueableCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new FakeLogger();
        $this->command = new TestQueueableCommand(name: 'bar');
    }

    protected function tearDown(): void
    {
        unset($this->logger, $this->command);
        parent::tearDown();
    }

    #[Test]
    public function it_logs_with_the_default_levels(): void
    {
        $passed = null;
        $middleware = new LogPushedToQueue($this->logger);

        $middleware($this->command, function (Command $command) use (&$passed): Result {
            $passed = $command;
            return Result::ok();
        });

        $context = ['command' => ['name' => 'bar']];

        $this->assertSame($this->command, $passed);
        $this->assertSame([
            ['level' => LogLevel::DEBUG, 'message' => 'Queuing command Tests:TestQueueableCommand.', 'context' => $context],
            ['level' => LogLevel::INFO, 'message' => 'Queued command Tests:TestQueueableCommand.', 'context' => $context],
        ], $this->logger->log);
    }

    #[Test]
    public function it_logs_with_custom_levels(): void
    {
        $middleware = new LogPushedToQueue($this->logger, LogLevel::NOTICE, LogLevel::WARNING);

        $middleware($this->command, fn (): Result => Result::ok());

        $this->assertSame([
            'NOTICE: Queuing command Tests:TestQueueableCommand.',
            'WARNING: Queued command Tests:TestQueueableCommand.',
        ], $this->logger->messages());
    }

    #[Test]
    public function it_uses_the_fully_qualified_class_name_when_there_is_no_module_basename(): void
    {
        $command = new class () implements Command {};
        $name = $command::class;
        $middleware = new LogPushedToQueue($this->logger);

        $middleware($command, fn (): Result => Result::ok());

        $this->assertSame([
            "DEBUG: Queuing command {$name}.",
            "INFO: Queued command {$name}.",
        ], $this->logger->messages());
    }

    #[Test]
    public function it_logs_before_the_next_closure_is_invoked(): void
    {
        $messages = null;
        $middleware = new LogPushedToQueue($this->logger);

        $middleware($this->command, function () use (&$messages): Result {
            $messages = $this->logger->messages();
            return Result::ok();
        });

        $this->assertSame(['DEBUG: Queuing command Tests:TestQueueableCommand.'], $messages);
    }

    #[Test]
    public function it_rethrows_an_exception_thrown_by_the_next_closure(): void
    {
        $middleware = new LogPushedToQueue($this->logger);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Boom!');

        $middleware($this->command, static function (): never {
            throw new LogicException('Boom!');
        });
    }
}
