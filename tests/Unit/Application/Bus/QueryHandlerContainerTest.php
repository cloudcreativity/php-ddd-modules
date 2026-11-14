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

use CloudCreativity\Modules\Bus\BusException;
use CloudCreativity\Modules\Bus\QueryHandler;
use CloudCreativity\Modules\Bus\QueryHandlerContainer;
use CloudCreativity\Modules\Contracts\Messaging\Query;
use CloudCreativity\Modules\Testing\FakeContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueryHandlerContainerTest extends TestCase
{
    #[Test]
    public function it_resolves_using_closure_bindings(): void
    {
        $a = new class () extends TestQueryHandler {};
        $b = $this->createStub(TestQueryHandler::class);

        $query1 = new class () implements Query {};
        $query2 = new class () implements Query {};

        $container = new QueryHandlerContainer();
        $container->bind($query1::class, fn () => $a);
        $container->bind($query2::class, fn () => $b);

        $this->assertEquals(new QueryHandler($a), $container->get($query1::class));
        $this->assertEquals(new QueryHandler($b), $container->get($query2::class));
    }

    #[Test]
    public function it_throws_when_no_closure_binding_exists_for_the_query(): void
    {
        $query1 = new class () implements Query {};
        $query2 = new class () implements Query {};

        $container = new QueryHandlerContainer();
        $container->bind($query1::class, fn () => new class () extends TestQueryHandler {});

        $this->expectException(BusException::class);
        $this->expectExceptionMessage('No query handler bound for query class: ' . $query2::class);

        $container->get($query2::class);
    }

    #[Test]
    public function it_resolves_via_psr_container(): void
    {
        $a = new class () extends TestQueryHandler {};
        $b = $this->createStub(TestQueryHandler::class);

        $query1 = new class () implements Query {};
        $query2 = new class () implements Query {};

        $psrContainer = new FakeContainer();
        $psrContainer->instance($a::class, $a);
        $psrContainer->instance($b::class, $b);

        $container = new QueryHandlerContainer($psrContainer);
        $container->bind($query1::class, $a::class);
        $container->bind($query2::class, $b::class);

        $this->assertEquals(new QueryHandler($a), $container->get($query1::class));
        $this->assertEquals(new QueryHandler($b), $container->get($query2::class));
    }

    #[Test]
    public function it_throws_when_no_psr_container_binding_exists_for_the_query(): void
    {
        $query1 = new class () implements Query {};
        $query2 = new class () implements Query {};

        $psrContainer = new FakeContainer();
        $psrContainer->instance(TestQueryHandler::class, new class () extends TestQueryHandler {});

        $container = new QueryHandlerContainer($psrContainer);
        $container->bind($query1::class, TestQueryHandler::class);

        $this->expectException(BusException::class);
        $this->expectExceptionMessage('No query handler bound for query class: ' . $query2::class);

        $container->get($query2::class);
    }

    #[Test]
    public function it_throws_when_binding_a_string_without_a_psr_container(): void
    {
        $container = new QueryHandlerContainer();

        $this->expectException(BusException::class);
        $this->expectExceptionMessage('Cannot use a string query handler binding without a PSR container.');

        $container->bind(TestQuery::class, TestQueryHandler::class);
    }
}
