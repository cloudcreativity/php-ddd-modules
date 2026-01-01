<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Bus\Loggable;

use CloudCreativity\Modules\Contracts\Bus\Loggable\ContextFactory;
use CloudCreativity\Modules\Contracts\Bus\Loggable\ContextProvider;
use CloudCreativity\Modules\Contracts\Bus\Message;
use CloudCreativity\Modules\Contracts\Toolkit\Result\Result;

final class SimpleContextFactory implements ContextFactory
{
    public function make(Message|Result $object): array
    {
        $object = match (true) {
            $object instanceof ContextProvider => $object,
            $object instanceof Result => new ResultDecorator($object),
            $object instanceof Message => new ObjectDecorator($object),
        };

        return $object->context();
    }
}
