<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Contracts\Application\Ports;

use Closure;

interface UnitOfWork
{
    /**
     * Is a unit of work currently active?
     *
     * Returns true when there is an open transaction that work will be committed or rolled back with, e.g. while a
     * callback passed to `execute()` is running. Implementations MAY also return true for a transaction that was not
     * started by this unit of work, if they cannot distinguish it.
     */
    public function active(): bool;

    /**
     * Is there no unit of work currently active?
     *
     * Returns the inverse of `active()`.
     */
    public function inactive(): bool;

    /**
     * Execute the callback in a transaction.
     *
     * @template TReturn
     * @param Closure(): TReturn $callback
     * @param int<1, max> $attempts
     * @return TReturn
     */
    public function execute(Closure $callback, int $attempts = 1): mixed;
}
