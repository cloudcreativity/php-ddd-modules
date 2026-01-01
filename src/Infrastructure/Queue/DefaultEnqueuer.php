<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Infrastructure\Queue;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DefaultEnqueuer
{
    /**
     * @param non-empty-string $enqueuer
     */
    public function __construct(public string $enqueuer)
    {
    }
}
