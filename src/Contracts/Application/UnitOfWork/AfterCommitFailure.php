<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Contracts\Application\UnitOfWork;

use CloudCreativity\Modules\Contracts\Application\ApplicationException;
use Throwable;

/**
 * Thrown when a unit of work committed, but one or more of its after commit callbacks failed.
 *
 * As the unit of work has committed, it cannot be rolled back or re-attempted. Catching this exception allows
 * calling code to distinguish this scenario from a unit of work that failed and did not commit.
 */
interface AfterCommitFailure extends ApplicationException
{
    /**
     * Get the exceptions thrown by the after commit callbacks, in the order they were thrown.
     *
     * @return non-empty-list<Throwable>
     */
    public function exceptions(): array;
}
