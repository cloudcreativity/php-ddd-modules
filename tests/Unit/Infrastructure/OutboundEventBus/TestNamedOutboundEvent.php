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

use CloudCreativity\Modules\Toolkit\Sensitive;

final class TestNamedOutboundEvent extends TestOutboundEvent
{
    public function __construct(
        public readonly string $name = 'foo',
        #[Sensitive] public readonly string $secret = 'secret',
    ) {
        parent::__construct();
    }
}
