<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Toolkit\Identifiers;

use CloudCreativity\Modules\Contracts\Toolkit\Identifiers\Uuid as IUuid;
use CloudCreativity\Modules\Toolkit\Identifiers\IsUuid;
use Ramsey\Uuid\UuidInterface;

final readonly class TestUuid implements IUuid
{
    use IsUuid;

    public function __construct(public UuidInterface $value)
    {
    }
}
