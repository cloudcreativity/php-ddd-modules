<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Toolkit\Pipeline;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Through
{
    /**
     * @var list<non-empty-string>
     */
    public array $pipes;

    /**
     * @param non-empty-string ...$pipes
     */
    public function __construct(string ...$pipes)
    {
        $this->pipes = array_values($pipes);
    }
}
