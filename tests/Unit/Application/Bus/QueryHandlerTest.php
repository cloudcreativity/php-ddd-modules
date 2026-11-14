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

use CloudCreativity\Modules\Bus\QueryHandler;
use CloudCreativity\Modules\Toolkit\Result\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueryHandlerTest extends TestCase
{
    #[Test]
    public function it_executes_the_query_and_returns_its_middleware(): void
    {
        $query = new TestQuery();

        $innerHandler = $this->createMock(TestQueryHandler::class);
        $innerHandler
            ->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo($query))
            ->willReturn($expected = Result::ok());

        $innerHandler
            ->expects($this->once())
            ->method('middleware')
            ->willReturn($middleware = ['Middleware1', 'Middleware2']);

        $handler = new QueryHandler($innerHandler);

        $this->assertSame($expected, $handler($query));
        $this->assertSame($middleware, $handler->middleware());
    }

    #[Test]
    public function it_does_not_have_execute_method(): void
    {
        $handler = new QueryHandler(new \DateTime());
        $query = new TestQuery();
        $queryClass = $query::class;

        $this->expectException(\AssertionError::class);
        $this->expectExceptionMessage(
            "Cannot dispatch \"{$queryClass}\" - handler \"DateTime\" does not have an execute method.",
        );

        $handler($query);
    }
}
