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

use CloudCreativity\Modules\Application\Bus\Middleware\ExecuteInUnitOfWork;
use CloudCreativity\Modules\Application\UnitOfWork\UnitOfWorkManager;
use CloudCreativity\Modules\Bus\Middleware\LogMessageDispatch;
use CloudCreativity\Modules\Testing\FakeContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MathCommandBusTest extends TestCase
{
    private FakeContainer $container;

    private MathCommandBus $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $container = new FakeContainer();
        $container->bind(AddCommandHandler::class, fn () => new AddCommandHandler(3));
        $container->bind(MultiplyCommandHandler::class, fn () => new MultiplyCommandHandler());
        $container->bind(LogMessageDispatch::class, fn () => new LogMessageDispatch($container->logger));
        $container->bind(ExecuteInUnitOfWork::class, fn () => new ExecuteInUnitOfWork(
            new UnitOfWorkManager($container->unitOfWork),
        ));

        $this->bus = new MathCommandBus($container);
    }

    protected function tearDown(): void
    {
        unset($this->container, $this->bus);
        parent::tearDown();
    }

    #[Test]
    public function it_dispatches_a_command_through_the_bus_middleware(): void
    {
        $command = new AddCommand(1, 2);

        $result = $this->bus->dispatch($command);

        $this->assertSame(6, $result->value());
        $this->assertCount(2, $this->container->logger);
        $this->assertSame(0, $this->container->unitOfWork->commits);
    }

    #[Test]
    public function it_dispatches_a_command_through_the_handler_middleware(): void
    {
        $command = new MultiplyCommand(10, 11);

        $result = $this->bus->dispatch($command);

        $this->assertSame(110, $result->value());
        $this->assertCount(2, $this->container->logger);
        $this->assertSame(1, $this->container->unitOfWork->commits);
    }
}
