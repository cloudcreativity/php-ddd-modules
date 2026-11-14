<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Testing;

use CloudCreativity\Modules\Contracts\Application\Ports\Queue;
use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Testing\FakeQueue;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FakeQueueTest extends TestCase
{
    #[Test]
    public function it_queues_commands(): void
    {
        $queue = new class () extends FakeQueue {};
        $queue->push($command1 = $this->createStub(Command::class));
        $queue->push($command2 = $this->createStub(Command::class));

        $this->assertInstanceOf(Queue::class, $queue);
        $this->assertCount(2, $queue);
        $this->assertSame([$command1, $command2], $queue->commands);
        $this->assertSame([$command1, $command2], iterator_to_array($queue));
        $this->assertSame($command1, $queue[0]);
        $this->assertSame($command2, $queue[1]);
    }

    #[Test]
    public function it_has_sole_command(): void
    {
        $queue = new class () extends FakeQueue {};
        $queue->push($command = $this->createStub(Command::class));

        $this->assertSame($command, $queue->sole());
    }

    #[Test]
    public function it_fails_when_there_is_no_sole_command(): void
    {
        $this->expectExceptionMessage('Expected one command in the queue but there are 0 commands.');
        $this->expectException(LogicException::class);

        $queue = new class () extends FakeQueue {};
        $queue->sole();
    }

    #[Test]
    public function it_fails_when_there_is_more_than_one_sole_command(): void
    {
        $this->expectExceptionMessage('Expected one command in the queue but there are 2 commands.');
        $this->expectException(LogicException::class);

        $queue = new class () extends FakeQueue {};
        $queue->push($this->createStub(Command::class));
        $queue->push($this->createStub(Command::class));
        $queue->sole();
    }
}
