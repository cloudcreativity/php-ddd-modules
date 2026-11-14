<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\DomainEventDispatching;

use CloudCreativity\Modules\Application\DomainEventDispatching\Dispatcher;
use CloudCreativity\Modules\Application\DomainEventDispatching\ListenTo;
use CloudCreativity\Modules\Toolkit\Pipeline\Through;

#[ListenTo(TestImmediateDomainEvent::class, ['Listener1', 'Listener2'])]
#[ListenTo(TestImmediateDomainEvent::class, 'Listener3')]
#[Through('Middleware1')]
final class TestDispatcherWithAttributes extends Dispatcher
{
}
