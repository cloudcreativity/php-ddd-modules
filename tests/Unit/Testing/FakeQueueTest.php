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
    private FakeQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queue = new class () extends FakeQueue {};
    }

    protected function tearDown(): void
    {
        unset($this->queue);
        parent::tearDown();
    }

    #[Test]
    public function it_is_a_queue(): void
    {
        $this->assertInstanceOf(Queue::class, $this->queue);
    }

    #[Test]
    public function it_is_empty_by_default(): void
    {
        $actual = [count($this->queue), iterator_to_array($this->queue)];

        $this->assertSame([0, []], $actual);
    }

    // -------------------------------------------------------------------------
    // push()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_queues_commands(): void
    {
        $command1 = $this->createStub(Command::class);
        $command2 = $this->createStub(Command::class);

        $this->queue->push($command1);
        $this->queue->push($command2);

        $this->assertSame([$command1, $command2], $this->queue->commands);
    }

    // -------------------------------------------------------------------------
    // count() and getIterator()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_counts_the_queued_commands(): void
    {
        $this->queue->commands = [
            $this->createStub(Command::class),
            $this->createStub(Command::class),
        ];

        $actual = count($this->queue);

        $this->assertSame(2, $actual);
    }

    #[Test]
    public function it_iterates_over_the_queued_commands(): void
    {
        $command1 = $this->createStub(Command::class);
        $command2 = $this->createStub(Command::class);
        $this->queue->commands = [$command1, $command2];

        $actual = iterator_to_array($this->queue);

        $this->assertSame([$command1, $command2], $actual);
    }

    // -------------------------------------------------------------------------
    // ArrayAccess
    // -------------------------------------------------------------------------

    #[Test]
    public function it_gets_a_command_by_offset(): void
    {
        $command1 = $this->createStub(Command::class);
        $command2 = $this->createStub(Command::class);
        $this->queue->commands = [$command1, $command2];

        $actual = [$this->queue[0], $this->queue[1]];

        $this->assertSame([$command1, $command2], $actual);
    }

    #[Test]
    public function it_checks_whether_an_offset_exists(): void
    {
        $this->queue->commands = [$this->createStub(Command::class)];

        $actual = [isset($this->queue[0]), isset($this->queue[1])];

        $this->assertSame([true, false], $actual);
    }

    #[Test]
    public function it_cannot_set_an_offset(): void
    {
        $command = $this->createStub(Command::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot set commands.');

        $this->queue[0] = $command;
    }

    #[Test]
    public function it_cannot_unset_an_offset(): void
    {
        $this->queue->commands = [$this->createStub(Command::class)];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot unset commands.');

        unset($this->queue[0]);
    }

    // -------------------------------------------------------------------------
    // sole()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_has_sole_command(): void
    {
        $command = $this->createStub(Command::class);
        $this->queue->commands = [$command];

        $actual = $this->queue->sole();

        $this->assertSame($command, $actual);
    }

    #[Test]
    public function it_fails_when_there_is_no_sole_command(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected one command in the queue but there are 0 commands.');

        $this->queue->sole();
    }

    #[Test]
    public function it_fails_when_there_is_more_than_one_sole_command(): void
    {
        $this->queue->commands = [
            $this->createStub(Command::class),
            $this->createStub(Command::class),
        ];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected one command in the queue but there are 2 commands.');

        $this->queue->sole();
    }
}
