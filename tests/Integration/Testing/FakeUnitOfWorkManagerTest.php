<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Integration\Testing;

use Closure;
use CloudCreativity\Modules\Application\ApplicationException;
use CloudCreativity\Modules\Application\UnitOfWork\AfterCommitCallbacksFailed;
use CloudCreativity\Modules\Application\UnitOfWork\UnitOfWorkManager;
use CloudCreativity\Modules\Testing\FakeExceptionReporter;
use CloudCreativity\Modules\Testing\FakeUnitOfWork;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Tests the real unit of work manager running over the fake unit of work.
 *
 * Consumers use this combination in their own tests, so this verifies the fake behaves
 * the same way as a real unit of work when driven by the manager. Note that the manager
 * handles re-attempts itself, executing the fake unit of work once per attempt. So each
 * attempt is recorded in the fake's sequence as a separate execution.
 */
final class FakeUnitOfWorkManagerTest extends TestCase
{
    private FakeUnitOfWork $unitOfWork;

    private FakeExceptionReporter $reporter;

    private UnitOfWorkManager $manager;

    /**
     * @var list<string>
     */
    private array $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new UnitOfWorkManager(
            $this->unitOfWork = new FakeUnitOfWork(),
            $this->reporter = new FakeExceptionReporter(),
        );

        $this->log = [];
    }

    protected function tearDown(): void
    {
        unset($this->manager, $this->unitOfWork, $this->reporter, $this->log);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Executing a unit of work
    // -------------------------------------------------------------------------

    #[Test]
    public function it_executes_the_callback_in_the_fake_unit_of_work(): void
    {
        $actual = $this->manager->execute(fn () => 'result');

        $this->assertSame('result', $actual);
        $this->assertSame(['execute:1/attempt:1', 'execute:1/commit:1'], $this->unitOfWork->sequence);
        $this->assertSame(1, $this->unitOfWork->executions);
        $this->assertSame(1, $this->unitOfWork->attempts);
        $this->assertSame(1, $this->unitOfWork->commits);
        $this->assertSame(0, $this->unitOfWork->rollbacks);
        $this->assertSame([], $this->unitOfWork->exceptions->reported);
        $this->assertSame([], $this->reporter->reported);
    }

    #[Test]
    public function it_executes_commit_callbacks_at_the_correct_point_in_the_fake_unit_of_work(): void
    {
        $snapshots = [];

        $this->manager->execute(function () use (&$snapshots): void {
            $snapshots['callback'] = [$this->unitOfWork->active(), $this->unitOfWork->sequence];
            $this->manager->afterCommit(function () use (&$snapshots): void {
                $snapshots['after'] = [$this->unitOfWork->active(), $this->unitOfWork->sequence];
            });
            $this->manager->beforeCommit(function () use (&$snapshots): void {
                $snapshots['before'] = [$this->unitOfWork->active(), $this->unitOfWork->sequence];
            });
        });

        $this->assertSame([
            'callback' => [true, ['execute:1/attempt:1']],
            'before' => [true, ['execute:1/attempt:1']],
            'after' => [false, ['execute:1/attempt:1', 'execute:1/commit:1']],
        ], $snapshots);
        $this->assertTrue($this->unitOfWork->inactive());
    }

    #[Test]
    public function it_executes_commit_callbacks_in_order(): void
    {
        $this->manager->execute(function (): void {
            $this->log[] = 'callback';
            $this->manager->beforeCommit($this->record('before1'));
            $this->manager->afterCommit($this->record('after1'));
            $this->manager->beforeCommit(function (): void {
                $this->log[] = 'before2';
                $this->manager->beforeCommit($this->record('before3'));
                $this->manager->afterCommit($this->record('after3'));
            });
            $this->manager->afterCommit($this->record('after2'));
        });

        $this->assertSame([
            'callback',
            'before1',
            'before2',
            'before3',
            'after1',
            'after2',
            'after3',
        ], $this->log);
        $this->assertSame(['execute:1/attempt:1', 'execute:1/commit:1'], $this->unitOfWork->sequence);
    }

    #[Test]
    public function it_cannot_start_a_unit_of_work_within_an_active_one(): void
    {
        $actual = null;

        try {
            $this->manager->execute(fn () => $this->manager->execute(fn () => 'result'));
        } catch (ApplicationException $ex) {
            $actual = $ex;
        }

        $this->assertInstanceOf(ApplicationException::class, $actual);
        $this->assertSame(
            'Not expecting unit of work manager to start a unit of work within an existing one.',
            $actual->getMessage(),
        );
        $this->assertSame(['execute:1/attempt:1', 'execute:1/rollback:1'], $this->unitOfWork->sequence);
        $this->assertSame([$actual], $this->unitOfWork->exceptions->reported);
        $this->assertTrue($this->unitOfWork->inactive());
    }

    #[Test]
    public function it_cannot_queue_a_before_commit_callback_from_an_after_commit_callback(): void
    {
        $actual = null;

        try {
            $this->manager->execute(function (): void {
                $this->manager->afterCommit(fn () => $this->manager->beforeCommit($this->record('before')));
            });
        } catch (ApplicationException $ex) {
            $actual = $ex;
        }

        $this->assertInstanceOf(ApplicationException::class, $actual);
        $this->assertSame(
            'Cannot queue a before commit callback when not executing a unit of work.',
            $actual->getMessage(),
        );
        $this->assertSame([], $this->log);
        $this->assertSame(['execute:1/attempt:1', 'execute:1/commit:1'], $this->unitOfWork->sequence);
    }

    // -------------------------------------------------------------------------
    // Rolling back and re-attempting
    // -------------------------------------------------------------------------

    #[Test]
    public function it_retries_until_the_fake_unit_of_work_commits(): void
    {
        $ex1 = new RuntimeException('An error occurred.');
        $ex2 = new RuntimeException('Another error occurred.');

        $actual = $this->manager->execute($this->failThenSucceed([$ex1, $ex2]), 3);

        $this->assertSame('result', $actual);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/rollback:1',
            'execute:2/attempt:1',
            'execute:2/rollback:1',
            'execute:3/attempt:1',
            'execute:3/commit:1',
        ], $this->unitOfWork->sequence);
        $this->assertSame(3, $this->unitOfWork->executions);
        $this->assertSame(3, $this->unitOfWork->attempts);
        $this->assertSame(1, $this->unitOfWork->commits);
        $this->assertSame(2, $this->unitOfWork->rollbacks);
        $this->assertSame([$ex1, $ex2], $this->unitOfWork->exceptions->reported);
        $this->assertSame([$ex1, $ex2], $this->reporter->reported);
    }

    #[Test]
    public function it_rethrows_the_last_exception_when_every_attempt_fails(): void
    {
        $ex1 = new RuntimeException('An error occurred.');
        $ex2 = new RuntimeException('Another error occurred.');
        $actual = null;

        try {
            $this->manager->execute($this->failThenSucceed([$ex1, $ex2]), 2);
        } catch (RuntimeException $ex) {
            $actual = $ex;
        }

        $this->assertSame($ex2, $actual);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/rollback:1',
            'execute:2/attempt:1',
            'execute:2/rollback:1',
        ], $this->unitOfWork->sequence);
        $this->assertSame(2, $this->unitOfWork->executions);
        $this->assertSame(2, $this->unitOfWork->attempts);
        $this->assertSame(0, $this->unitOfWork->commits);
        $this->assertSame(2, $this->unitOfWork->rollbacks);
        $this->assertSame([$ex1, $ex2], $this->unitOfWork->exceptions->reported);
        $this->assertSame([$ex1], $this->reporter->reported); // only "swallowed" exceptions are reported
        $this->assertTrue($this->unitOfWork->inactive());
    }

    #[Test]
    public function it_rolls_back_the_fake_unit_of_work_when_a_before_commit_callback_fails(): void
    {
        $ex = new LogicException('Boom!');
        $actual = null;

        try {
            $this->manager->execute(function () use ($ex): void {
                $this->manager->beforeCommit(fn () => throw $ex);
                $this->manager->afterCommit($this->record('after'));
            });
        } catch (LogicException $e) {
            $actual = $e;
        }

        $this->assertSame($ex, $actual);
        $this->assertSame([], $this->log);
        $this->assertSame(['execute:1/attempt:1', 'execute:1/rollback:1'], $this->unitOfWork->sequence);
        $this->assertSame(0, $this->unitOfWork->commits);
        $this->assertSame([$ex], $this->unitOfWork->exceptions->reported);
        $this->assertSame([], $this->reporter->reported);
    }

    #[Test]
    public function it_retries_when_a_before_commit_callback_fails(): void
    {
        $ex = new LogicException('Boom!');
        $count = 0;

        $this->manager->execute(function () use ($ex, &$count): void {
            $count++;
            $attempt = $count;
            $this->manager->beforeCommit(function () use ($ex, $attempt): void {
                $this->log[] = 'before:' . $attempt;
                if ($attempt === 1) {
                    throw $ex;
                }
            });
            $this->manager->afterCommit($this->record('after:' . $attempt));
        }, 2);

        $this->assertSame(['before:1', 'before:2', 'after:2'], $this->log);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/rollback:1',
            'execute:2/attempt:1',
            'execute:2/commit:1',
        ], $this->unitOfWork->sequence);
        $this->assertSame([$ex], $this->unitOfWork->exceptions->reported);
        $this->assertSame([$ex], $this->reporter->reported);
    }

    #[Test]
    public function it_forgets_callbacks_registered_by_a_failed_attempt(): void
    {
        $ex = new LogicException('Boom!');
        $count = 0;

        $this->manager->execute(function () use ($ex, &$count): void {
            $count++;
            $this->manager->beforeCommit($this->record('before:' . $count));
            $this->manager->afterCommit($this->record('after:' . $count));
            if ($count === 1) {
                throw $ex;
            }
        }, 2);

        $this->assertSame(['before:2', 'after:2'], $this->log);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/rollback:1',
            'execute:2/attempt:1',
            'execute:2/commit:1',
        ], $this->unitOfWork->sequence);
    }

    // -------------------------------------------------------------------------
    // Failing after commit callbacks
    // -------------------------------------------------------------------------

    #[Test]
    public function it_does_not_retry_when_an_after_commit_callback_fails(): void
    {
        $ex = new LogicException('Boom!');
        $actual = null;

        try {
            $this->manager->execute(function () use ($ex): void {
                $this->manager->afterCommit(function () use ($ex): void {
                    $this->log[] = 'after1';
                    throw $ex;
                });
                $this->manager->afterCommit($this->record('after2'));
            }, 3);
        } catch (LogicException $e) {
            $actual = $e;
        }

        $this->assertSame($ex, $actual);
        $this->assertSame(['after1', 'after2'], $this->log);
        $this->assertSame(['execute:1/attempt:1', 'execute:1/commit:1'], $this->unitOfWork->sequence);
        $this->assertSame(1, $this->unitOfWork->attempts);
        $this->assertSame(0, $this->unitOfWork->rollbacks);
        $this->assertSame([], $this->unitOfWork->exceptions->reported);
        $this->assertSame([], $this->reporter->reported);
    }

    #[Test]
    public function it_executes_all_after_commit_callbacks_when_several_fail(): void
    {
        $ex1 = new LogicException('Boom 1!');
        $ex2 = new RuntimeException('Boom 2!');
        $actual = null;

        try {
            $this->manager->execute(function () use ($ex1, $ex2): void {
                $this->manager->afterCommit(fn () => throw $ex1);
                $this->manager->afterCommit($this->record('after2'));
                $this->manager->afterCommit(fn () => throw $ex2);
            }, 3);
        } catch (AfterCommitCallbacksFailed $e) {
            $actual = $e;
        }

        $this->assertInstanceOf(AfterCommitCallbacksFailed::class, $actual);
        $this->assertSame([$ex1, $ex2], $actual->exceptions());
        $this->assertSame(['after2'], $this->log);
        $this->assertSame(['execute:1/attempt:1', 'execute:1/commit:1'], $this->unitOfWork->sequence);
        $this->assertSame([], $this->unitOfWork->exceptions->reported);
        $this->assertSame([], $this->reporter->reported);
    }

    // -------------------------------------------------------------------------
    // After commit callbacks that are queued while draining
    //
    // Once committed, the after commit callbacks are drained as a FIFO queue.
    // Any after commit callback registered while draining is added to the end
    // of the queue.
    // -------------------------------------------------------------------------

    #[Test]
    public function it_queues_an_after_commit_callback_registered_while_draining(): void
    {
        $this->manager->execute(function (): void {
            $this->manager->afterCommit(function (): void {
                $this->log[] = 'after1:start';
                $this->manager->afterCommit($this->record('after3'));
                $this->log[] = 'after1:end';
            });
            $this->manager->afterCommit($this->record('after2'));
        });

        $this->assertSame(['after1:start', 'after1:end', 'after2', 'after3'], $this->log);
        $this->assertSame(['execute:1/attempt:1', 'execute:1/commit:1'], $this->unitOfWork->sequence);
    }

    #[Test]
    public function it_executes_remaining_callbacks_when_a_queued_callback_fails(): void
    {
        $ex = new LogicException('Boom!');
        $actual = null;

        try {
            $this->manager->execute(function () use ($ex): void {
                $this->manager->afterCommit(function () use ($ex): void {
                    $this->log[] = 'after1';
                    $this->manager->afterCommit(function () use ($ex): void {
                        $this->log[] = 'after3';
                        throw $ex;
                    });
                });
                $this->manager->afterCommit(function (): void {
                    $this->log[] = 'after2';
                    $this->manager->afterCommit($this->record('after4'));
                });
            });
        } catch (LogicException $e) {
            $actual = $e;
        }

        $this->assertSame($ex, $actual);
        $this->assertSame(['after1', 'after2', 'after3', 'after4'], $this->log);
        $this->assertSame(['execute:1/attempt:1', 'execute:1/commit:1'], $this->unitOfWork->sequence);
        $this->assertSame([], $this->unitOfWork->exceptions->reported);
        $this->assertSame([], $this->reporter->reported);
    }

    #[Test]
    public function it_collects_the_failure_of_a_queued_callback_with_other_failures(): void
    {
        $ex1 = new LogicException('Boom 1!');
        $ex2 = new RuntimeException('Boom 2!');
        $actual = null;

        try {
            $this->manager->execute(function () use ($ex1, $ex2): void {
                $this->manager->afterCommit(fn () => $this->manager->afterCommit(fn () => throw $ex2));
                $this->manager->afterCommit(fn () => throw $ex1);
            });
        } catch (AfterCommitCallbacksFailed $e) {
            $actual = $e;
        }

        $this->assertInstanceOf(AfterCommitCallbacksFailed::class, $actual);
        $this->assertSame([$ex1, $ex2], $actual->exceptions());
        $this->assertSame(['execute:1/attempt:1', 'execute:1/commit:1'], $this->unitOfWork->sequence);
    }

    // -------------------------------------------------------------------------
    // Starting a unit of work from an after commit callback
    // -------------------------------------------------------------------------

    #[Test]
    public function it_starts_a_new_unit_of_work_from_an_after_commit_callback(): void
    {
        $nested = null;

        $actual = $this->manager->execute(function () use (&$nested): string {
            $this->manager->afterCommit(function () use (&$nested): void {
                $nested = $this->manager->execute(fn () => 'nested-result');
            });
            return 'outer-result';
        });

        $this->assertSame('outer-result', $actual);
        $this->assertSame('nested-result', $nested);
        $this->assertSame(2, $this->unitOfWork->executions);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/commit:1',
            'execute:2/attempt:1',
            'execute:2/commit:1',
        ], $this->unitOfWork->sequence);
        $this->assertSame(2, $this->unitOfWork->attempts);
        $this->assertSame(2, $this->unitOfWork->commits);
        $this->assertSame(0, $this->unitOfWork->rollbacks);
        $this->assertTrue($this->unitOfWork->inactive());
    }

    #[Test]
    public function it_executes_the_nested_unit_of_work_while_the_fake_unit_of_work_is_active(): void
    {
        $flags = [];

        $this->manager->execute(function () use (&$flags): void {
            $flags['outer'] = $this->unitOfWork->active();
            $this->manager->afterCommit(function () use (&$flags): void {
                $flags['after:start'] = $this->unitOfWork->active();
                $this->manager->execute(function () use (&$flags): void {
                    $flags['nested'] = $this->unitOfWork->active();
                });
                $flags['after:end'] = $this->unitOfWork->active();
            });
        });

        $this->assertSame([
            'outer' => true,
            'after:start' => false,
            'nested' => true,
            'after:end' => false,
        ], $flags);
    }

    /**
     * Regression for the nested unit of work resetting the outer after commit queue.
     */
    #[Test]
    public function it_executes_remaining_after_commit_callbacks_after_a_nested_unit_of_work(): void
    {
        $this->manager->execute(function (): void {
            $this->manager->afterCommit(function (): void {
                $this->log[] = 'after1';
                $this->manager->execute($this->record('nested'));
            });
            $this->manager->afterCommit($this->record('after2'));
            $this->manager->afterCommit($this->record('after3'));
        });

        $this->assertSame(['after1', 'nested', 'after2', 'after3'], $this->log);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/commit:1',
            'execute:2/attempt:1',
            'execute:2/commit:1',
        ], $this->unitOfWork->sequence);
    }

    /**
     * The nested unit of work executes its own before commit callbacks, but its after commit callbacks
     * are added to the end of the queue being drained. So the nested unit of work returns before they
     * are executed.
     */
    #[Test]
    public function it_executes_the_commit_callbacks_of_a_nested_unit_of_work(): void
    {
        $snapshots = [];

        $this->manager->execute(function () use (&$snapshots): void {
            $this->manager->beforeCommit(function () use (&$snapshots): void {
                $snapshots['outer:before'] = [$this->unitOfWork->active(), $this->unitOfWork->sequence];
            });
            $this->manager->afterCommit(function () use (&$snapshots): void {
                $this->manager->execute(function () use (&$snapshots): void {
                    $this->manager->afterCommit(function () use (&$snapshots): void {
                        $snapshots['nested:after'] = [$this->unitOfWork->active(), $this->unitOfWork->sequence];
                    });
                    $this->manager->beforeCommit(function () use (&$snapshots): void {
                        $snapshots['nested:before'] = [$this->unitOfWork->active(), $this->unitOfWork->sequence];
                    });
                });
                $snapshots['outer:after1:end'] = [$this->unitOfWork->active(), $this->unitOfWork->sequence];
            });
            $this->manager->afterCommit(function () use (&$snapshots): void {
                $snapshots['outer:after2'] = [$this->unitOfWork->active(), $this->unitOfWork->sequence];
            });
        });

        $this->assertSame([
            'outer:before' => [true, ['execute:1/attempt:1']],
            'nested:before' => [true, ['execute:1/attempt:1', 'execute:1/commit:1', 'execute:2/attempt:1']],
            'outer:after1:end' => [false, [
                'execute:1/attempt:1',
                'execute:1/commit:1',
                'execute:2/attempt:1',
                'execute:2/commit:1',
            ]],
            'outer:after2' => [false, [
                'execute:1/attempt:1',
                'execute:1/commit:1',
                'execute:2/attempt:1',
                'execute:2/commit:1',
            ]],
            'nested:after' => [false, [
                'execute:1/attempt:1',
                'execute:1/commit:1',
                'execute:2/attempt:1',
                'execute:2/commit:1',
            ]],
        ], $snapshots);
    }

    #[Test]
    public function it_queues_an_after_commit_callback_registered_after_a_nested_unit_of_work(): void
    {
        $this->manager->execute(function (): void {
            $this->manager->afterCommit(function (): void {
                $this->manager->execute(function (): void {
                    $this->log[] = 'nested';
                    $this->manager->afterCommit($this->record('nested:after'));
                });
                $this->manager->afterCommit($this->record('after3'));
                $this->log[] = 'after1:end';
            });
            $this->manager->afterCommit($this->record('after2'));
        });

        $this->assertSame(['nested', 'after1:end', 'after2', 'nested:after', 'after3'], $this->log);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/commit:1',
            'execute:2/attempt:1',
            'execute:2/commit:1',
        ], $this->unitOfWork->sequence);
    }

    #[Test]
    public function it_starts_a_unit_of_work_from_several_after_commit_callbacks(): void
    {
        $this->manager->execute(function (): void {
            $this->manager->afterCommit(fn () => $this->manager->execute($this->record('nested1')));
            $this->manager->afterCommit(fn () => $this->manager->execute($this->record('nested2')));
        });

        $this->assertSame(['nested1', 'nested2'], $this->log);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/commit:1',
            'execute:2/attempt:1',
            'execute:2/commit:1',
            'execute:3/attempt:1',
            'execute:3/commit:1',
        ], $this->unitOfWork->sequence);
        $this->assertSame(3, $this->unitOfWork->commits);
    }

    #[Test]
    public function it_starts_a_unit_of_work_from_a_nested_after_commit_callback(): void
    {
        $this->manager->execute(function (): void {
            $this->log[] = 'outer';
            $this->manager->afterCommit(function (): void {
                $this->manager->execute(function (): void {
                    $this->log[] = 'nested1';
                    $this->manager->afterCommit(fn () => $this->manager->execute($this->record('nested2')));
                    $this->manager->afterCommit($this->record('nested1:after'));
                });
            });
            $this->manager->afterCommit($this->record('outer:after'));
        });

        $this->assertSame(['outer', 'nested1', 'outer:after', 'nested2', 'nested1:after'], $this->log);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/commit:1',
            'execute:2/attempt:1',
            'execute:2/commit:1',
            'execute:3/attempt:1',
            'execute:3/commit:1',
        ], $this->unitOfWork->sequence);
        $this->assertSame(3, $this->unitOfWork->executions);
        $this->assertSame(3, $this->unitOfWork->attempts);
        $this->assertSame(3, $this->unitOfWork->commits);
    }

    #[Test]
    public function it_retries_a_nested_unit_of_work(): void
    {
        $ex = new LogicException('Boom!');
        $count = 0;

        $this->manager->execute(function () use ($ex, &$count): void {
            $this->manager->afterCommit(function () use ($ex, &$count): void {
                $this->manager->execute(function () use ($ex, &$count): void {
                    $count++;
                    $this->manager->beforeCommit($this->record('nested:before:' . $count));
                    $this->manager->afterCommit($this->record('nested:after:' . $count));
                    if ($count === 1) {
                        throw $ex;
                    }
                }, 2);
            });
            $this->manager->afterCommit($this->record('outer:after'));
        });

        $this->assertSame(['nested:before:2', 'outer:after', 'nested:after:2'], $this->log);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/commit:1',
            'execute:2/attempt:1',
            'execute:2/rollback:1',
            'execute:3/attempt:1',
            'execute:3/commit:1',
        ], $this->unitOfWork->sequence);
        $this->assertSame(3, $this->unitOfWork->executions);
        $this->assertSame(3, $this->unitOfWork->attempts);
        $this->assertSame(2, $this->unitOfWork->commits);
        $this->assertSame(1, $this->unitOfWork->rollbacks);
        $this->assertSame([$ex], $this->unitOfWork->exceptions->reported);
        $this->assertSame([$ex], $this->reporter->reported);
    }

    #[Test]
    public function it_executes_remaining_after_commit_callbacks_when_a_nested_unit_of_work_fails(): void
    {
        $ex = new LogicException('Boom!');
        $actual = null;

        try {
            $this->manager->execute(function () use ($ex): void {
                $this->manager->afterCommit(function () use ($ex): void {
                    $this->manager->execute(function () use ($ex): void {
                        $this->manager->beforeCommit($this->record('nested:before'));
                        $this->manager->afterCommit($this->record('nested:after'));
                        throw $ex;
                    });
                });
                $this->manager->afterCommit($this->record('outer:after'));
            });
        } catch (LogicException $e) {
            $actual = $e;
        }

        $this->assertSame($ex, $actual);
        $this->assertSame(['outer:after'], $this->log);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/commit:1',
            'execute:2/attempt:1',
            'execute:2/rollback:1',
        ], $this->unitOfWork->sequence);
        $this->assertSame(1, $this->unitOfWork->commits);
        $this->assertSame(1, $this->unitOfWork->rollbacks);
        $this->assertSame([$ex], $this->unitOfWork->exceptions->reported);
        $this->assertSame([], $this->reporter->reported);
        $this->assertTrue($this->unitOfWork->inactive());
    }

    /**
     * As the nested after commit callbacks are queued, their failure is thrown by the outer unit of work,
     * not the nested one.
     */
    #[Test]
    public function it_executes_remaining_after_commit_callbacks_when_a_nested_after_commit_callback_fails(): void
    {
        $ex = new LogicException('Boom!');
        $actual = null;

        try {
            $this->manager->execute(function () use ($ex): void {
                $this->manager->afterCommit(function () use ($ex): void {
                    $this->log[] = $this->manager->execute(function () use ($ex): string {
                        $this->manager->afterCommit(fn () => throw $ex);
                        $this->manager->afterCommit($this->record('nested:after'));
                        return 'nested:result';
                    });
                });
                $this->manager->afterCommit($this->record('outer:after'));
            });
        } catch (LogicException $e) {
            $actual = $e;
        }

        $this->assertSame($ex, $actual);
        $this->assertSame(['nested:result', 'outer:after', 'nested:after'], $this->log);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/commit:1',
            'execute:2/attempt:1',
            'execute:2/commit:1',
        ], $this->unitOfWork->sequence);
        $this->assertSame(0, $this->unitOfWork->rollbacks);
        $this->assertSame([], $this->unitOfWork->exceptions->reported);
        $this->assertSame([], $this->reporter->reported);
    }

    #[Test]
    public function it_cannot_start_a_unit_of_work_within_a_nested_unit_of_work(): void
    {
        $actual = null;

        try {
            $this->manager->execute(function (): void {
                $this->manager->afterCommit(function (): void {
                    $this->manager->execute(fn () => $this->manager->execute(fn () => null));
                });
                $this->manager->afterCommit($this->record('outer:after'));
            });
        } catch (ApplicationException $ex) {
            $actual = $ex;
        }

        $this->assertInstanceOf(ApplicationException::class, $actual);
        $this->assertSame(
            'Not expecting unit of work manager to start a unit of work within an existing one.',
            $actual->getMessage(),
        );
        $this->assertSame(['outer:after'], $this->log);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/commit:1',
            'execute:2/attempt:1',
            'execute:2/rollback:1',
        ], $this->unitOfWork->sequence);
        $this->assertSame([$actual], $this->unitOfWork->exceptions->reported);
    }

    #[Test]
    public function it_cannot_queue_a_before_commit_callback_from_an_after_commit_callback_after_a_nested_unit_of_work(): void
    {
        $actual = null;

        try {
            $this->manager->execute(function (): void {
                $this->manager->afterCommit(function (): void {
                    $this->manager->execute(fn () => null);
                    $this->manager->beforeCommit($this->record('before'));
                });
            });
        } catch (ApplicationException $ex) {
            $actual = $ex;
        }

        $this->assertInstanceOf(ApplicationException::class, $actual);
        $this->assertSame(
            'Cannot queue a before commit callback when not executing a unit of work.',
            $actual->getMessage(),
        );
        $this->assertSame([], $this->log);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/commit:1',
            'execute:2/attempt:1',
            'execute:2/commit:1',
        ], $this->unitOfWork->sequence);
    }

    // -------------------------------------------------------------------------
    // Resetting state once the unit of work is complete
    // -------------------------------------------------------------------------

    #[Test]
    public function it_is_reset_after_a_nested_unit_of_work(): void
    {
        $this->manager->execute(function (): void {
            $this->manager->afterCommit(function (): void {
                $this->manager->execute(function (): void {
                    $this->manager->afterCommit($this->record('nested:after'));
                });
            });
        });

        $actual = $this->manager->execute(function (): string {
            $this->manager->beforeCommit($this->record('second:before'));
            $this->manager->afterCommit($this->record('second:after'));
            return 'result';
        });

        $this->assertSame('result', $actual);
        $this->assertSame(['nested:after', 'second:before', 'second:after'], $this->log);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/commit:1',
            'execute:2/attempt:1',
            'execute:2/commit:1',
            'execute:3/attempt:1',
            'execute:3/commit:1',
        ], $this->unitOfWork->sequence);
        $this->assertTrue($this->unitOfWork->inactive());
    }

    #[Test]
    public function it_is_reset_after_a_nested_unit_of_work_fails(): void
    {
        try {
            $this->manager->execute(function (): void {
                $this->manager->afterCommit(function (): void {
                    $this->manager->execute(fn () => throw new LogicException('Boom!'));
                });
            });
        } catch (LogicException) {
            // expected
        }

        $actual = $this->manager->execute(function (): string {
            $this->manager->afterCommit($this->record('second:after'));
            return 'result';
        });

        $this->assertSame('result', $actual);
        $this->assertSame(['second:after'], $this->log);
        $this->assertSame([
            'execute:1/attempt:1',
            'execute:1/commit:1',
            'execute:2/attempt:1',
            'execute:2/rollback:1',
            'execute:3/attempt:1',
            'execute:3/commit:1',
        ], $this->unitOfWork->sequence);
    }

    #[Test]
    public function it_cannot_queue_an_after_commit_callback_once_the_unit_of_work_has_completed(): void
    {
        $this->manager->execute(function (): void {
            $this->manager->afterCommit(fn () => $this->manager->execute(fn () => null));
        });

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Cannot queue an after commit callback when not executing a unit of work.');

        $this->manager->afterCommit($this->record('after'));
    }

    #[Test]
    public function it_cannot_queue_a_before_commit_callback_once_the_unit_of_work_has_completed(): void
    {
        $this->manager->execute(function (): void {
            $this->manager->afterCommit(fn () => $this->manager->execute(fn () => null));
        });

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Cannot queue a before commit callback when not executing a unit of work.');

        $this->manager->beforeCommit($this->record('before'));
    }

    /**
     * Create a callback that records the provided entry in the log.
     *
     * @return Closure(): void
     */
    private function record(string $entry): Closure
    {
        return function () use ($entry): void {
            $this->log[] = $entry;
        };
    }

    /**
     * Create a callback that throws each of the provided exceptions in turn, then returns a result.
     *
     * @param list<Throwable> $exceptions
     * @return Closure(): string
     */
    private function failThenSucceed(array $exceptions): Closure
    {
        return function () use (&$exceptions): string {
            $ex = array_shift($exceptions);

            if ($ex) {
                throw $ex;
            }

            return 'result';
        };
    }
}
