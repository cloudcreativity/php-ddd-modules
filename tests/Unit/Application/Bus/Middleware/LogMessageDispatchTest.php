<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\Bus\Middleware;

use CloudCreativity\Modules\Bus\Middleware\LogMessageDispatch;
use CloudCreativity\Modules\Bus\SanitizedMessage;
use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Contracts\Messaging\IntegrationEvent;
use CloudCreativity\Modules\Contracts\Messaging\Message;
use CloudCreativity\Modules\Contracts\Messaging\Query;
use CloudCreativity\Modules\Testing\FakeLogger;
use CloudCreativity\Modules\Tests\Unit\Application\Bus\TestCommand;
use CloudCreativity\Modules\Toolkit\Result\Result;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class LogMessageDispatchTest extends TestCase
{
    private FakeLogger $logger;

    private Command $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new FakeLogger();
        $this->message = new class () implements Command {};
    }

    protected function tearDown(): void
    {
        unset($this->logger, $this->message);
        parent::tearDown();
    }

    #[Test]
    public function it_logs_with_the_default_levels(): void
    {
        $expected = Result::ok();
        $name = $this->message::class;
        $middleware = new LogMessageDispatch($this->logger);

        $actual = $middleware($this->message, function (Message $received) use ($expected) {
            $this->assertSame($this->message, $received);
            return $expected;
        });

        $this->assertSame($expected, $actual);
        $this->assertEquals([
            [
                'level' => LogLevel::DEBUG,
                'message' => "Bus dispatching {$name}.",
                'context' => ['command' => new SanitizedMessage($this->message)->context()],
            ],
            [
                'level' => LogLevel::INFO,
                'message' => "Bus dispatched {$name}.",
                'context' => ['result' => $expected->context()],
            ],
        ], $this->logger->log);
    }

    #[Test]
    public function it_logs_with_custom_levels(): void
    {
        $expected = Result::failed('Something went wrong.');
        $name = $this->message::class;
        $middleware = new LogMessageDispatch($this->logger, LogLevel::NOTICE, LogLevel::WARNING);

        $actual = $middleware($this->message, function (Message $received) use ($expected) {
            $this->assertSame($this->message, $received);
            return $expected;
        });

        $this->assertSame($expected, $actual);
        $this->assertEquals([
            [
                'level' => LogLevel::NOTICE,
                'message' => "Bus dispatching {$name}.",
                'context' => ['command' => new SanitizedMessage($this->message)->context()],
            ],
            [
                'level' => LogLevel::WARNING,
                'message' => "Bus dispatched {$name}.",
                'context' => ['result' => $expected->context()],
            ],
        ], $this->logger->log);
    }

    #[Test]
    public function it_logs_after_the_next_closure_is_invoked(): void
    {
        $expected = new LogicException();
        $message = $this->createStub(Query::class);
        $name = $message::class;
        $middleware = new LogMessageDispatch($this->logger);
        $actual = null;

        try {
            $middleware($message, static function () use ($expected) {
                throw $expected;
            });
        } catch (LogicException $ex) {
            $actual = $ex;
        }

        $this->assertSame($expected, $actual);
        $this->assertEquals([
            [
                'level' => LogLevel::DEBUG,
                'message' => "Bus dispatching {$name}.",
                'context' => ['query' => new SanitizedMessage($message)->context()],
            ],
        ], $this->logger->log);
    }

    #[Test]
    public function it_logs_an_integration_event(): void
    {
        $event = $this->createStub(IntegrationEvent::class);
        $name = $event::class;
        $middleware = new LogMessageDispatch($this->logger);

        $actual = $middleware($event, fn () => null);

        $this->assertNull($actual);
        $this->assertEquals([
            [
                'level' => LogLevel::DEBUG,
                'message' => "Bus dispatching {$name}.",
                'context' => ['event' => new SanitizedMessage($event)->context()],
            ],
            ['level' => LogLevel::INFO, 'message' => "Bus dispatched {$name}.", 'context' => []],
        ], $this->logger->log);
    }

    #[Test]
    public function it_logs_a_message_that_is_not_a_command_query_or_event(): void
    {
        $message = $this->createStub(Message::class);
        $expected = Result::ok();
        $name = $message::class;
        $middleware = new LogMessageDispatch($this->logger);

        $actual = $middleware($message, fn () => $expected);

        $this->assertSame($expected, $actual);
        $this->assertEquals([
            [
                'level' => LogLevel::DEBUG,
                'message' => "Bus dispatching {$name}.",
                'context' => ['message' => new SanitizedMessage($message)->context()],
            ],
            [
                'level' => LogLevel::INFO,
                'message' => "Bus dispatched {$name}.",
                'context' => ['result' => $expected->context()],
            ],
        ], $this->logger->log);
    }

    #[Test]
    public function it_logs_the_module_basename_and_sanitized_message(): void
    {
        $command = new TestCommand(fail: true);
        $middleware = new LogMessageDispatch($this->logger);

        $middleware($command, fn () => null);

        $this->assertSame([
            [
                'level' => LogLevel::DEBUG,
                'message' => 'Bus dispatching Tests:TestCommand.',
                'context' => ['command' => ['fail' => true]],
            ],
            ['level' => LogLevel::INFO, 'message' => 'Bus dispatched Tests:TestCommand.', 'context' => []],
        ], $this->logger->log);
    }
}
