<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Integration\Application\InboundEventBus;

use CloudCreativity\Modules\Application\InboundEventBus\Middleware\HandleInUnitOfWork;
use CloudCreativity\Modules\Application\InboundEventBus\Middleware\LogInboundEvent;
use CloudCreativity\Modules\Application\UnitOfWork\UnitOfWorkManager;
use CloudCreativity\Modules\Testing\FakeContainer;
use PHPUnit\Framework\TestCase;

class MathEventBusTest extends TestCase
{
    public function test(): void
    {
        $a = new NumbersAddedHandler();
        $b = new NumbersSubtractedHandler();
        $c = new DefaultHandler();

        $container = new FakeContainer();
        $container->bind(NumbersAddedHandler::class, fn () => $a);
        $container->bind(NumbersSubtractedHandler::class, fn () => $b);
        $container->bind(DefaultHandler::class, fn () => $c);
        $container->bind(LogInboundEvent::class, fn () => new LogInboundEvent($container->logger));
        $container->bind(HandleInUnitOfWork::class, fn () => new HandleInUnitOfWork(
            new UnitOfWorkManager($container->unitOfWork),
        ));

        $bus = new MathEventBus($container);

        $bus->dispatch($ev1 = new NumbersAdded(1, 2, 3));
        $bus->dispatch($ev2 = new NumbersSubtracted(10, 6, 4));
        $bus->dispatch($ev3 = new NumbersDivided(12, 3, 4));

        $this->assertSame([$ev1], $a->handled);
        $this->assertSame([$ev2], $b->handled);
        $this->assertSame([$ev3], $c->handled);
        $this->assertCount(6, $container->logger);
        $this->assertSame(1, $container->unitOfWork->commits);
    }
}
