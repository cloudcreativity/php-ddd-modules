<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Application\Messages;

use CloudCreativity\Modules\Contracts\Application\Messages\DispatchThroughMiddleware;
use ReflectionClass;

trait HandlesMessages
{
    public function middleware(): array
    {
        $middleware = [];

        $reflection = new ReflectionClass($this->handler);

        foreach ($reflection->getAttributes(Through::class) as $attribute) {
            $instance = $attribute->newInstance();
            $middleware[] = $instance->pipe;
        }

        if ($this->handler instanceof DispatchThroughMiddleware) {
            $middleware = [
                ...$middleware,
                ...$this->handler->middleware(),
            ];
        }

        return $middleware;
    }
}
