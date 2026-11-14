<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\InboundEventBus;

use CloudCreativity\Modules\Bus\InboundEventDispatcher;
use CloudCreativity\Modules\Bus\SwallowInboundEvent;
use CloudCreativity\Modules\Bus\WithDefault;
use CloudCreativity\Modules\Bus\WithEvent;
use CloudCreativity\Modules\Toolkit\Pipeline\Through;

#[Through('Middleware1')]
#[WithDefault(SwallowInboundEvent::class)]
#[WithEvent(TestInboundEvent::class, TestEventHandler::class)]
final class TestInboundEventDispatcherWithAttributes extends InboundEventDispatcher
{
}
