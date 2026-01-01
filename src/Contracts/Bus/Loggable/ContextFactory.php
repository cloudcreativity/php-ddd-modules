<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Contracts\Bus\Loggable;

use CloudCreativity\Modules\Contracts\Bus\Message;
use CloudCreativity\Modules\Contracts\Toolkit\Result\Result;

interface ContextFactory
{
    /**
     * Make log context for the provided object.
     *
     * @param Message|Result<mixed> $object
     * @return array<string, mixed>
     */
    public function make(Message|Result $object): array;
}
