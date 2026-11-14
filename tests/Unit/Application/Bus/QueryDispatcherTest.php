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

use Closure;
use CloudCreativity\Modules\Bus\QueryDispatcher;
use CloudCreativity\Modules\Bus\PsrPipeContainer;
use CloudCreativity\Modules\Contracts\Bus\QueryHandler;
use CloudCreativity\Modules\Contracts\Bus\QueryHandlerContainer;
use CloudCreativity\Modules\Contracts\Messaging\Query;
use CloudCreativity\Modules\Testing\FakeContainer;
use CloudCreativity\Modules\Toolkit\Result\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueryDispatcherTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $sequence = [];

    #[Test]
    public function it_dispatches_a_query(): void
    {
        $query = $this->createStub(Query::class);
        $handler = $this->createMock(QueryHandler::class);
        $handlers = $this->createMock(QueryHandlerContainer::class);
        $dispatcher = new class ($handlers) extends QueryDispatcher {};

        $handlers
            ->expects($this->once())
            ->method('get')
            ->with($query::class)
            ->willReturn($handler);

        $handler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($query))
            ->willReturn($expected = Result::ok());

        $actual = $dispatcher->dispatch($query);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_dispatches_a_query_through_middleware(): void
    {
        $query1 = new TestQuery();
        $query2 = new TestQuery();
        $query3 = new TestQuery();
        $query4 = new TestQuery();
        $handler = $this->createMock(QueryHandler::class);
        $handlers = $this->createMock(QueryHandlerContainer::class);

        $middleware1 = function (TestQuery $query, Closure $next) use ($query1, $query2) {
            $this->assertSame($query1, $query);
            $this->sequence[] = 'before1';
            $result = $next($query2);
            $this->sequence[] = 'after1';
            return $result;
        };

        $middleware2 = function (TestQuery $query, Closure $next) use ($query2, $query3) {
            $this->assertSame($query2, $query);
            $this->sequence[] = 'before2';
            $result = $next($query3);
            $this->sequence[] = 'after2';
            return $result;
        };

        $middleware3 = function (TestQuery $query, Closure $next) use ($query3, $query4) {
            $this->assertSame($query3, $query);
            $this->sequence[] = 'before3';
            $result = $next($query4);
            $this->sequence[] = 'after3';
            return $result;
        };

        $middleware = new PsrPipeContainer();
        $middleware->bind('MyFirstMiddleware', fn () => $middleware1);
        $middleware->bind('MySecondMiddleware', fn () => $middleware2);

        $dispatcher = new class ($handlers, $middleware) extends QueryDispatcher {};
        $dispatcher->through(['MyFirstMiddleware']);

        $handlers
            ->expects($this->once())
            ->method('get')
            ->with($query1::class)
            ->willReturnCallback(function () use ($handler) {
                $this->assertSame(['before1'], $this->sequence);
                return $handler;
            });

        $handler
            ->expects($this->once())
            ->method('middleware')
            ->willReturn(['MySecondMiddleware', $middleware3]);

        $handler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($query4))
            ->willReturn($expected = Result::ok());

        $actual = $dispatcher->dispatch($query1);

        $this->assertSame($expected, $actual);
        $this->assertSame([
            'before1',
            'before2',
            'before3',
            'after3',
            'after2',
            'after1',
        ], $this->sequence);
    }

    #[Test]
    public function it_autowires_handlers_and_middleware_from_a_psr_container(): void
    {
        $query = new TestQuery();
        $expected = Result::ok();
        $handler = $this->createMock(TestQueryHandler::class);
        $container = new FakeContainer();
        $container->instance(TestQueryHandler::class, $handler);
        $container->instance('Middleware1', function (TestQuery $actual, Closure $next) {
            $this->sequence[] = 'Middleware1';
            return $next($actual);
        });
        $dispatcher = new TestQueryDispatcherWithAttributes($container);

        $handler
            ->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo($query))
            ->willReturnCallback(function () use ($expected) {
                $this->sequence[] = 'handler';
                return $expected;
            });

        $actual = $dispatcher->dispatch($query);

        $this->assertSame($expected, $actual);
        $this->assertSame(['Middleware1', 'handler'], $this->sequence);
    }

    #[Test]
    public function it_autowires_middleware_but_not_handlers_with_a_custom_handler_container(): void
    {
        $query = new TestQuery();
        $expected = Result::ok();
        $middleware = new PsrPipeContainer();
        $middleware->bind('Middleware1', fn () => function (TestQuery $actual, Closure $next) {
            $this->sequence[] = 'Middleware1';
            return $next($actual);
        });
        $handler = $this->createMock(QueryHandler::class);
        $handlers = $this->createMock(QueryHandlerContainer::class);
        $dispatcher = new TestQueryDispatcherWithAttributes($handlers, $middleware);

        $handlers
            ->expects($this->once())
            ->method('get')
            ->with(TestQuery::class)
            ->willReturn($handler);

        $handler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($query))
            ->willReturnCallback(function () use ($expected) {
                $this->sequence[] = 'handler';
                return $expected;
            });

        $actual = $dispatcher->dispatch($query);

        $this->assertSame($expected, $actual);
        $this->assertSame(['Middleware1', 'handler'], $this->sequence);
    }
}
