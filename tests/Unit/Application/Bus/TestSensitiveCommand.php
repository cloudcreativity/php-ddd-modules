<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\Bus;

use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Toolkit\Sensitive;

final readonly class TestSensitiveCommand implements Command
{
    public function __construct(
        public string $name,
        #[Sensitive]
        public string $password,
        public ?int $age = null,
        private string $secret = 'secret',
    ) {
    }

    public function secret(): string
    {
        return $this->secret;
    }
}
