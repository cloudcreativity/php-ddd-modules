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
use CloudCreativity\Modules\Toolkit\Sensitive;

final readonly class TestQueueableCommand implements Command
{
    public function __construct(
        public string $name = 'foo',
        #[Sensitive] public string $secret = 'secret',
    ) {
    }
}
