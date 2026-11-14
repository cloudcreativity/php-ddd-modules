<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Infrastructure\OutboundEventBus;

use CloudCreativity\Modules\Infrastructure\OutboundEventBus\ComponentPublisher;
use CloudCreativity\Modules\Infrastructure\OutboundEventBus\DefaultPublisher;
use CloudCreativity\Modules\Infrastructure\OutboundEventBus\Publishes;
use CloudCreativity\Modules\Toolkit\Pipeline\Through;

#[DefaultPublisher('DefaultPublisher')]
#[Publishes(TestNamedOutboundEvent::class, TestPublisher::class)]
#[Through('Middleware1', 'Middleware2')]
final class TestComponentPublisherWithAttributes extends ComponentPublisher
{
}
