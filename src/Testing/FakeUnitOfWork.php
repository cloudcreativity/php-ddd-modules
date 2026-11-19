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

use Closure;
use CloudCreativity\Modules\Contracts\Application\Ports\UnitOfWork;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class FakeUnitOfWork implements UnitOfWork
{
    /**
     * The number of times `execute()` has been called, excluding calls that were rejected.
     */
    public int $executions = 0;

    /**
     * The number of attempts, across every execution.
     */
    public int $attempts = 0;

    /**
     * The number of commits, across every execution.
     */
    public int $commits = 0;

    /**
     * The number of rollbacks, across every execution.
     */
    public int $rollbacks = 0;

    /**
     * The sequence of what happened, across every execution.
     *
     * Each entry is in the format `execute:<execution>/<event>:<attempt>`, where the event is `attempt`, `commit` or
     * `rollback`, and the attempt is the attempt number within that execution.
     *
     * @var list<string>
     */
    public array $sequence = [];

    private bool $active = false;

    public function __construct(public FakeExceptionReporter $exceptions = new FakeExceptionReporter())
    {
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function inactive(): bool
    {
        return !$this->active;
    }

    public function execute(Closure $callback, int $attempts = 1): mixed
    {
        if ($attempts < 1) {
            throw new InvalidArgumentException('The number of attempts must be at least 1.');
        }

        if ($this->active) {
            throw new RuntimeException('Fake unit of work is already active.');
        }

        $this->executions++;

        for ($i = 1; $i <= $attempts; $i++) {
            $this->active = true;
            try {
                $this->sequence[] = sprintf('execute:%d/attempt:%d', $this->executions, $i);
                $this->attempts++;
                $result = $callback();
                $this->sequence[] = sprintf('execute:%d/commit:%d', $this->executions, $i);
                $this->commits++;
                return $result;
            } catch (Throwable $ex) {
                $this->sequence[] = sprintf('execute:%d/rollback:%d', $this->executions, $i);
                $this->rollbacks++;
                $this->exceptions->report($ex);

                if ($i === $attempts) {
                    throw $ex;
                }
            } finally {
                $this->active = false;
            }
        }

        throw new RuntimeException('Failed to execute unit of work.');
    }
}
