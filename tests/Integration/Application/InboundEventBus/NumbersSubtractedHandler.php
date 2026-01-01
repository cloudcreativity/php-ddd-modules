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
use CloudCreativity\Modules\Application\Messages\Through;

#[Through(HandleInUnitOfWork::class)]
final class NumbersSubtractedHandler
{
    /**
     * @var array<NumbersSubtracted>
     */
    public array $handled = [];

    public function handle(NumbersSubtracted $event): void
    {
        $this->handled[] = $event;
    }
}
