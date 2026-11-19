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

use Closure;
use Throwable;

interface UnitOfWorkManager
{
    /**
     * Execute the provided closure within a unit of work.
     *
     * If the closure (or committing the unit of work) fails, the exception is thrown and the unit of work will not
     * have committed. If the unit of work commits but one or more after commit callbacks fail, an
     * `AfterCommitFailure` exception is thrown instead. This allows calling code to distinguish the two scenarios.
     *
     * @template TReturn
     * @param Closure(): TReturn $callback
     * @param int<1, max> $attempts
     * @return TReturn
     * @throws AfterCommitFailure if the commit succeeded but an exception was thrown afterwards.
     * @throws Throwable if an exception occurs during the unit of work.
     */
    public function execute(Closure $callback, int $attempts = 1): mixed;

    /**
     * Register a callback to be executed before the unit of work is committed.
     *
     * @param callable(): void $callback
     */
    public function beforeCommit(callable $callback): void;

    /**
     * Register a callback to be executed after the unit of work is committed.
     *
     * @param callable(): void $callback
     */
    public function afterCommit(callable $callback): void;
}
