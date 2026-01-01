<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Infrastructure\Queue;

use Attribute;
use CloudCreativity\Modules\Contracts\Bus\Command;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Queues
{
    /**
     * @param class-string<Command> $command
     * @param class-string $enqueuer
     */
    public function __construct(public string $command, public string $enqueuer)
    {
    }
}
