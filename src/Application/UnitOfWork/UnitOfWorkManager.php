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

use Closure;
use CloudCreativity\Modules\Application\ApplicationException;
use CloudCreativity\Modules\Contracts\Application\Ports\ExceptionReporter;
use CloudCreativity\Modules\Contracts\Application\Ports\UnitOfWork;
use CloudCreativity\Modules\Contracts\Application\UnitOfWork\UnitOfWorkManager as IUnitOfWorkManager;
use Throwable;

/**
 * Coordinates work that must happen before and after a unit of work commits.
 *
 * Before commit callbacks are executed within the unit of work, so they are re-attempted with it. After commit
 * callbacks are collected while the unit of work is executing, and are only executed once it has committed.
 *
 * After commit callbacks are executed as a single FIFO queue. A callback that registers another after commit
 * callback, or that starts (and commits) a new unit of work, adds to the end of that queue. The queue is drained by
 * the outermost call to `execute()`, so a unit of work started by an after commit callback returns before its own
 * after commit callbacks are executed.
 *
 * If any after commit callbacks fail, the outermost call to `execute()` throws an `AfterCommitFailure` once the
 * queue has been drained. This indicates that the unit of work did commit, even though it threw.
 */
final class UnitOfWorkManager implements IUnitOfWorkManager
{
    /**
     * Callbacks to execute before the active unit of work commits.
     *
     * @var callable[]
     */
    private array $beforeCommit = [];

    /**
     * Callbacks to execute after the active unit of work commits.
     *
     * These are only added to the queue once the unit of work has committed, so that callbacks registered
     * by a failed attempt are discarded rather than executed.
     *
     * @var callable[]
     */
    private array $afterCommit = [];

    /**
     * Whether a unit of work is currently executing, i.e. it has not yet committed or rolled back.
     */
    private bool $active = false;

    /**
     * Whether the queue of after commit callbacks is being drained.
     */
    private bool $draining = false;

    /**
     * FIFO queue of after commit callbacks, for units of work that have committed.
     *
     * @var callable[]
     */
    private array $queue = [];

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ?ExceptionReporter $reporter = null,
    ) {
    }

    /**
     * Execute the callback in a unit of work.
     *
     * A unit of work cannot be started while another is executing. It can however be started by an after commit
     * callback, as the previous unit of work has committed at that point. In this scenario, the after commit
     * callbacks of the new unit of work are added to the end of the queue that is already being drained, rather
     * than being executed before this method returns. Any failures of those callbacks are therefore thrown by the
     * outermost call to this method.
     */
    public function execute(Closure $callback, int $attempts = 1): mixed
    {
        if ($this->active) {
            throw new ApplicationException(
                'Not expecting unit of work manager to start a unit of work within an existing one.',
            );
        }

        if ($attempts < 1) {
            throw new ApplicationException('Attempts must be greater than zero.');
        }

        try {
            $result = $this->retry($callback, $attempts);
            // The unit of work has committed: so its after commit callbacks are queued outside the retry
            // scope. They must not cause the unit of work to be re-attempted if they fail.
            $this->queue = [
                ...$this->queue,
                ...$this->afterCommit,
            ];
        } finally {
            $this->reset();
        }

        // If we are already draining, this unit of work was started by an after commit callback. Its own
        // after commit callbacks will be executed by the drain that is already in progress.
        if (!$this->draining) {
            $this->drain();
        }

        return $result;
    }

    private function retry(Closure $callback, int $attempts): mixed
    {
        try {
            return $this->transaction($callback);
        } catch (Throwable $ex) {
            if ($attempts === 1) {
                throw $ex;
            }

            // Report "swallowed" exceptions.
            $this->reporter?->report($ex);
        }

        return $this->retry($callback, $attempts - 1);
    }

    private function transaction(Closure $callback): mixed
    {
        try {
            return $this->unitOfWork->execute(function () use ($callback) {
                $this->active = true;
                $value = $callback();
                $this->executeBeforeCommit();
                return $value;
            });
        } catch (Throwable $ex) {
            // Forget any callbacks registered by the failed attempt, so they are not executed twice.
            $this->reset();
            throw $ex;
        }
    }

    /**
     * Reset the state of the unit of work that was executing.
     *
     * This does not affect the queue of after commit callbacks, which may be being drained for a unit of work
     * that has already committed.
     */
    private function reset(): void
    {
        $this->active = false;
        $this->beforeCommit = [];
        $this->afterCommit = [];
    }

    /**
     * Register a callback to execute before the unit of work commits.
     *
     * This is only possible while a unit of work is executing. After commit callbacks cannot register before
     * commit callbacks, as the unit of work has already committed - unless they do so within a new unit of work.
     */
    public function beforeCommit(callable $callback): void
    {
        if ($this->active) {
            $this->beforeCommit[] = $callback;
            return;
        }

        throw new ApplicationException('Cannot queue a before commit callback when not executing a unit of work.');
    }

    /**
     * Register a callback to execute after the unit of work commits.
     *
     * While a unit of work is executing, the callback is held until it commits. When called by an after commit
     * callback (i.e. while the queue is being drained), the callback is added to the end of the queue.
     */
    public function afterCommit(callable $callback): void
    {
        if ($this->active) {
            $this->afterCommit[] = $callback;
            return;
        }

        if ($this->draining) {
            $this->queue[] = $callback;
            return;
        }

        throw new ApplicationException('Cannot queue an after commit callback when not executing a unit of work.');
    }

    private function executeBeforeCommit(): void
    {
        while ($callback = array_shift($this->beforeCommit)) {
            $callback();
        }
    }

    /**
     * Drain the queue of after commit callbacks.
     *
     * Callbacks are executed in FIFO order, including any that are added to the queue while it is being drained.
     *
     * As the unit of work has been committed, a failing callback must not prevent the remaining callbacks from being
     * executed. Once the queue is drained, any failures are wrapped in a single `AfterCommitCallbacksFailed`
     * exception - even if only one callback failed. This allows calling code to know that the unit of work did
     * commit. This includes failures of after commit callbacks belonging to a unit of work started while draining.
     *
     * @throws AfterCommitCallbacksFailed
     */
    private function drain(): void
    {
        $this->draining = true;
        $exceptions = [];

        while ($callback = array_shift($this->queue)) {
            try {
                $callback();
            } catch (Throwable $ex) {
                $exceptions[] = $ex;
            }
        }

        $this->draining = false;

        if ($exceptions !== []) {
            throw new AfterCommitCallbacksFailed($exceptions);
        }
    }
}
