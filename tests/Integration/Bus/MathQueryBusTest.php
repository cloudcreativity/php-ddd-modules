<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Integration\Bus;

use Closure;
use CloudCreativity\Modules\Bus\Middleware\LogMessageDispatch;
use CloudCreativity\Modules\Testing\FakeContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MathQueryBusTest extends TestCase
{
    private FakeContainer $container;

    private MathQueryBus $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $container = new FakeContainer();
        $container->bind(SubtractQueryHandler::class, fn () => new SubtractQueryHandler(3));
        $container->bind(DivideQueryHandler::class, fn () => new DivideQueryHandler());
        $container->bind(LogMessageDispatch::class, fn () => new LogMessageDispatch($container->logger));
        $container->bind('division-modifier', fn () => function (DivideQuery $query, Closure $next): mixed {
            $query = new DivideQuery($query->a * 10, $query->b);
            return $next($query);
        });

        $this->bus = new MathQueryBus($container);
    }

    protected function tearDown(): void
    {
        unset($this->container, $this->bus);
        parent::tearDown();
    }

    #[Test]
    public function it_dispatches_a_query_through_the_bus_middleware(): void
    {
        $query = new SubtractQuery(1, 2);

        $result = $this->bus->dispatch($query);

        $this->assertSame(1 - 2 - 3, $result->value());
        $this->assertCount(2, $this->container->logger);
    }

    #[Test]
    public function it_dispatches_a_query_through_the_handler_middleware(): void
    {
        $query = new DivideQuery(12, 3);

        $result = $this->bus->dispatch($query);

        $this->assertEquals(120 / 3, $result->value());
        $this->assertCount(2, $this->container->logger);
    }
}
