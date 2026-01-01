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

use CloudCreativity\Modules\Application\InboundEventBus\InboundEventDispatcher;
use CloudCreativity\Modules\Application\InboundEventBus\Middleware\LogInboundEvent;
use CloudCreativity\Modules\Application\InboundEventBus\WithDefault;
use CloudCreativity\Modules\Application\InboundEventBus\WithEvent;
use CloudCreativity\Modules\Application\Messages\Through;

#[Through(LogInboundEvent::class)]
#[WithDefault(DefaultHandler::class)]
#[WithEvent(NumbersAdded::class, NumbersAddedHandler::class)]
#[WithEvent(NumbersSubtracted::class, NumbersSubtractedHandler::class)]
final class MathEventBus extends InboundEventDispatcher
{
}
