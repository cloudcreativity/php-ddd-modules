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

use CloudCreativity\Modules\Infrastructure\Queue\ComponentQueue;
use CloudCreativity\Modules\Infrastructure\Queue\DefaultEnqueuer;
use CloudCreativity\Modules\Infrastructure\Queue\Queues;
use CloudCreativity\Modules\Tests\Unit\Application\Bus\TestCommand;
use CloudCreativity\Modules\Toolkit\Pipeline\Through;

#[DefaultEnqueuer('DefaultEnqueuer')]
#[Queues(TestQueueableCommand::class, TestEnqueuer::class)]
#[Queues([TestCommand::class], TestEnqueuer::class)]
#[Through('Middleware1', 'Middleware2')]
final class TestComponentQueueWithAttributes extends ComponentQueue
{
}
