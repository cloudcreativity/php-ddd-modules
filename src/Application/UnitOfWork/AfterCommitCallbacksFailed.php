<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Application\UnitOfWork;

use CloudCreativity\Modules\Contracts\Application\UnitOfWork\AfterCommitFailure;
use RuntimeException;
use Throwable;

final class AfterCommitCallbacksFailed extends RuntimeException implements AfterCommitFailure
{
    /**
     * @param non-empty-list<Throwable> $exceptions
     */
    public function __construct(private readonly array $exceptions)
    {
        assert(count($exceptions) > 0, 'Expecting at least one exception.');

        $count = count($exceptions);

        parent::__construct(
            $count === 1 ? '1 after commit callback failed.' : sprintf('%d after commit callbacks failed.', $count),
            0,
            array_first($exceptions),
        );
    }

    public function exceptions(): array
    {
        return $this->exceptions;
    }
}
