<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\Bus;

use CloudCreativity\Modules\Bus\CommandHandler;
use CloudCreativity\Modules\Toolkit\Result\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandHandlerTest extends TestCase
{
    #[Test]
    public function it_executes_the_command(): void
    {
        $command = new TestCommand();
        $innerHandler = $this->createMock(TestCommandHandler::class);
        $handler = new CommandHandler($innerHandler);

        $innerHandler
            ->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo($command))
            ->willReturn($expected = Result::ok());

        $actual = $handler($command);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_returns_the_middleware_of_the_inner_handler(): void
    {
        $innerHandler = $this->createStub(TestCommandHandler::class);
        $innerHandler->method('middleware')->willReturn(['Middleware1', 'Middleware2']);
        $handler = new CommandHandler($innerHandler);

        $actual = $handler->middleware();

        $this->assertSame(['Middleware1', 'Middleware2'], $actual);
    }

    #[Test]
    public function it_returns_middleware_from_the_through_attribute(): void
    {
        $handler = new CommandHandler(new TestCommandHandlerWithThrough());

        $actual = $handler->middleware();

        $this->assertSame(['Middleware1', 'Middleware2'], $actual);
    }

    #[Test]
    public function it_returns_no_middleware_when_none_is_declared(): void
    {
        $handler = new CommandHandler(new \stdClass());

        $actual = $handler->middleware();

        $this->assertSame([], $actual);
    }
}
