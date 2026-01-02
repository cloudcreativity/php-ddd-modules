<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Bus\Logging;

use CloudCreativity\Modules\Contracts\Toolkit\Contextual;
use CloudCreativity\Modules\Contracts\Toolkit\Result\Error;
use CloudCreativity\Modules\Contracts\Toolkit\Result\Result;

final readonly class SanitizedResult
{
    /**
     * @param Result<mixed> $result
     */
    public function __construct(private Result $result)
    {
    }

    public function success(): bool
    {
        return $this->result->didSucceed();
    }

    public function value(): ?Contextual
    {
        $value = $this->result->safe();

        return $value instanceof Contextual ? $value : null;
    }

    /**
     * @return array<string,mixed>
     */
    public function meta(): array
    {
        return $this->result->meta()->all();
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function errors(): array
    {
        return array_map(
            fn (Error $error): array => $this->error($error),
            $this->result->errors()->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function error(Error $error): array
    {
        return array_filter([
            'code' => $error->code(),
            'key' => $error->key(),
            'message' => $error->message(),
        ]);
    }
}
