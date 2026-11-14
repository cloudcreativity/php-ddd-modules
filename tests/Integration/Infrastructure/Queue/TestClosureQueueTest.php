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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TestClosureQueueTest extends TestCase
{
    #[Test]
    public function it_queues_a_command(): void
    {
        $queued = [];

        $container = new FakeContainer();
        $container->bind(LogPushedToQueue::class, fn () => new LogPushedToQueue($container->logger));

        $publisher = new TestClosureQueue(
            fn: function ($event) use (&$queued) {
                $queued[] = $event;
            },
            middleware: $container,
        );

        $command = new AddCommand(1, 2);
        $publisher->push($command);

        $this->assertSame([$command], $queued);
        $this->assertCount(2, $container->logger);
    }
}
