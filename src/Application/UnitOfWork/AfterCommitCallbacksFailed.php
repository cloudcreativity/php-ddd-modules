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

use CloudCreativity\Modules\Contracts\Application\ApplicationException;
use RuntimeException;
use Throwable;

final class AfterCommitCallbacksFailed extends RuntimeException implements ApplicationException
{
    /**
     * @param non-empty-list<Throwable> $exceptions
     */
    public function __construct(private readonly array $exceptions)
    {
        assert(count($exceptions) > 1, 'Expecting more than one exception.');

        parent::__construct(
            sprintf('%d after commit callbacks failed.', count($exceptions)),
            0,
            $exceptions[0],
        );
    }

    /**
     * Get the exceptions thrown by the after commit callbacks, in the order they were thrown.
     *
     * @return non-empty-list<Throwable>
     */
    public function exceptions(): array
    {
        return $this->exceptions;
    }
}
