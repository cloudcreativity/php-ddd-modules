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

final class MathEventBusTest extends TestCase
{
    private FakeContainer $container;

    private NumbersAddedHandler $addedHandler;

    private NumbersSubtractedHandler $subtractedHandler;

    private DefaultEventHandler $defaultHandler;

    private MathEventBus $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addedHandler = $a = new NumbersAddedHandler();
        $this->subtractedHandler = $b = new NumbersSubtractedHandler();
        $this->defaultHandler = $c = new DefaultEventHandler();

        $this->container = $container = new FakeContainer();
        $container->bind(NumbersAddedHandler::class, fn () => $a);
        $container->bind(NumbersSubtractedHandler::class, fn () => $b);
        $container->bind(DefaultEventHandler::class, fn () => $c);
        $container->bind(LogMessageDispatch::class, fn () => new LogMessageDispatch($container->logger));
        $container->bind(ExecuteInUnitOfWork::class, fn () => new ExecuteInUnitOfWork(
            new UnitOfWorkManager($container->unitOfWork),
        ));

        $this->bus = new MathEventBus($container);
    }

    protected function tearDown(): void
    {
        unset(
            $this->container,
            $this->addedHandler,
            $this->subtractedHandler,
            $this->defaultHandler,
            $this->bus,
        );
        parent::tearDown();
    }

    #[Test]
    public function it_dispatches_an_event_to_its_handler(): void
    {
        $event = new NumbersAdded(1, 2, 3);

        $this->bus->dispatch($event);

        $this->assertSame([$event], $this->addedHandler->handled);
        $this->assertSame([], $this->subtractedHandler->handled);
        $this->assertSame([], $this->defaultHandler->handled);
        $this->assertCount(2, $this->container->logger);
        $this->assertSame(0, $this->container->unitOfWork->commits);
    }

    #[Test]
    public function it_dispatches_an_event_through_the_handler_middleware(): void
    {
        $event = new NumbersSubtracted(10, 6, 4);

        $this->bus->dispatch($event);

        $this->assertSame([], $this->addedHandler->handled);
        $this->assertSame([$event], $this->subtractedHandler->handled);
        $this->assertSame([], $this->defaultHandler->handled);
        $this->assertCount(2, $this->container->logger);
        $this->assertSame(1, $this->container->unitOfWork->commits);
    }

    #[Test]
    public function it_dispatches_an_event_to_the_default_handler(): void
    {
        $event = new NumbersDivided(12, 3, 4);

        $this->bus->dispatch($event);

        $this->assertSame([], $this->addedHandler->handled);
        $this->assertSame([], $this->subtractedHandler->handled);
        $this->assertSame([$event], $this->defaultHandler->handled);
        $this->assertCount(2, $this->container->logger);
        $this->assertSame(0, $this->container->unitOfWork->commits);
    }
}
