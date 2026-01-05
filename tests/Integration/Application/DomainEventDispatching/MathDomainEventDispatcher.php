<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Integration\Application\DomainEventDispatching;

use CloudCreativity\Modules\Application\DomainEventDispatching\ListenTo;
use CloudCreativity\Modules\Application\DomainEventDispatching\Middleware\LogDomainEventDispatch;
use CloudCreativity\Modules\Application\DomainEventDispatching\UnitOfWorkAwareDispatcher;
use CloudCreativity\Modules\Toolkit\Pipeline\Through;

#[ListenTo(NumbersAdded::class, TestDomainListener::class)]
#[ListenTo(NumbersSubtracted::class, [
    TestDomainListener::class,
    TestDomainListener::class,
])]
#[Through(LogDomainEventDispatch::class)]
final class MathDomainEventDispatcher extends UnitOfWorkAwareDispatcher
{
}
