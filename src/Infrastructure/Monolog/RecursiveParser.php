<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Infrastructure\Monolog;

final readonly class RecursiveParser implements ValueParser
{
    public function __construct(private ValueParser $innerParser)
    {
    }

    public function parse(mixed $value): mixed
    {
        if (!is_iterable($value)) {
            return $this->innerParser->parse($value);
        }

        $parsed = [];

        foreach ($value as $key => $item) {
            if (is_string($key) || is_int($key)) {
                $parsed[$key] = $this->parse($item);
            }
        }

        return $parsed;
    }
}
