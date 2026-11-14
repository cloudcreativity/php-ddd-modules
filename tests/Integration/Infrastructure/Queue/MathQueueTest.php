<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Integration\Infrastructure\Queue;

use CloudCreativity\Modules\Infrastructure\Queue\Middleware\LogPushedToQueue;
use CloudCreativity\Modules\Testing\FakeContainer;
use CloudCreativity\Modules\Tests\Integration\Bus\AddCommand;
use CloudCreativity\Modules\Tests\Integration\Bus\FloorCommand;
use CloudCreativity\Modules\Tests\Integration\Bus\MultiplyCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MathQueueTest extends TestCase
{
    private FakeContainer $container;

    private AddCommandEnqueuer $addEnqueuer;

    private MultiplyCommandEnqueuer $multiplyEnqueuer;

    private TestDefaultEnqueuer $defaultEnqueuer;

    private MathQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addEnqueuer = $a = new AddCommandEnqueuer();
        $this->multiplyEnqueuer = $b = new MultiplyCommandEnqueuer();
        $this->defaultEnqueuer = $c = new TestDefaultEnqueuer();

        $this->container = $container = new FakeContainer();
        $container->bind(AddCommandEnqueuer::class, fn () => $a);
        $container->bind(MultiplyCommandEnqueuer::class, fn () => $b);
        $container->bind(TestDefaultEnqueuer::class, fn () => $c);
        $container->bind(LogPushedToQueue::class, fn () => new LogPushedToQueue($container->logger));

        $this->queue = new MathQueue($container);
    }

    protected function tearDown(): void
    {
        unset(
            $this->container,
            $this->addEnqueuer,
            $this->multiplyEnqueuer,
            $this->defaultEnqueuer,
            $this->queue,
        );
        parent::tearDown();
    }

    #[Test]
    public function it_queues_a_command_via_its_enqueuer(): void
    {
        $command = new AddCommand(1, 2);

        $this->queue->push($command);

        $this->assertSame([$command], $this->addEnqueuer->queued);
        $this->assertSame([], $this->multiplyEnqueuer->queued);
        $this->assertSame([], $this->defaultEnqueuer->queued);
        $this->assertCount(2, $this->container->logger);
    }

    #[Test]
    public function it_queues_a_command_via_an_enqueuer_registered_with_a_list_of_commands(): void
    {
        $command = new MultiplyCommand(10, 6);

        $this->queue->push($command);

        $this->assertSame([], $this->addEnqueuer->queued);
        $this->assertSame([$command], $this->multiplyEnqueuer->queued);
        $this->assertSame([], $this->defaultEnqueuer->queued);
        $this->assertCount(2, $this->container->logger);
    }

    #[Test]
    public function it_queues_a_command_via_the_default_enqueuer(): void
    {
        $command = new FloorCommand(99.9);

        $this->queue->push($command);

        $this->assertSame([], $this->addEnqueuer->queued);
        $this->assertSame([], $this->multiplyEnqueuer->queued);
        $this->assertSame([$command], $this->defaultEnqueuer->queued);
        $this->assertCount(2, $this->container->logger);
    }
}
