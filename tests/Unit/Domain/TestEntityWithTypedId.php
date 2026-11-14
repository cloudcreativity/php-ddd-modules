<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Domain;

use CloudCreativity\Modules\Contracts\Domain\Entity;
use CloudCreativity\Modules\Domain\IsEntityWithTypedId;
use CloudCreativity\Modules\Toolkit\Identifiers\UuidV7;

final readonly class TestEntityWithTypedId implements Entity
{
    use IsEntityWithTypedId;

    public function __construct(public UuidV7 $id, public string $name = 'Jane Doe')
    {
    }
}
