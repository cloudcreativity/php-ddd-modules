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

use CloudCreativity\Modules\Application\Bus\CommandQueuer;
use CloudCreativity\Modules\Testing\FakeQueue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandQueuerTest extends TestCase
{
    private FakeQueue $queue;

    private CommandQueuer $queuer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queuer = new class (
            $this->queue = new class () extends FakeQueue {},
        ) extends CommandQueuer {};
    }

    protected function tearDown(): void
    {
        unset($this->queue, $this->queuer);
        parent::tearDown();
    }

    #[Test]
    public function it_queues_the_command(): void
    {
        $command = new TestCommand();

        $this->queuer->queue($command);

        $this->assertSame([$command], $this->queue->commands);
    }
}
