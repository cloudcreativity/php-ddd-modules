<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Testing;

use Countable;
use Psr\Log\AbstractLogger;
use Stringable;

final class FakeLogger extends AbstractLogger implements Countable
{
    /**
     * @var array<array{level: mixed, message: string, context: mixed[]}>
     */
    public array $log = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->log[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return array<string>
     */
    public function messages(): array
    {
        return array_map(
            fn (array $entry): string => implode(': ', array_filter([
                is_scalar($entry['level']) ? strtoupper((string) $entry['level']) : null,
                $entry['message'],
            ])),
            $this->log,
        );
    }

    public function count(): int
    {
        return count($this->log);
    }
}
