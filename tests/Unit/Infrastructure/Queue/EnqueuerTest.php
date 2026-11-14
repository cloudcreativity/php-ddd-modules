<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Infrastructure\Queue;

use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Infrastructure\Queue\Enqueuer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnqueuerTest extends TestCase
{
    #[Test]
    public function it_pushes_the_command_to_the_inner_enqueuer(): void
    {
        $command = $this->createStub(Command::class);
        $innerEnqueuer = $this->createMock(TestEnqueuer::class);

        $innerEnqueuer
            ->expects($this->once())
            ->method('push')
            ->with($this->identicalTo($command));

        $enqueuer = new Enqueuer($innerEnqueuer);
        $enqueuer($command);
    }
}
