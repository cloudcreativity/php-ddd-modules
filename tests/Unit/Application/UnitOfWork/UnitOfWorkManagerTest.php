<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\UnitOfWork;

use Closure;
use CloudCreativity\Modules\Application\ApplicationException;
use CloudCreativity\Modules\Application\UnitOfWork\AfterCommitCallbacksFailed;
use CloudCreativity\Modules\Application\UnitOfWork\UnitOfWorkManager;
use CloudCreativity\Modules\Contracts\Application\Ports\UnitOfWork;
use CloudCreativity\Modules\Contracts\Application\UnitOfWork\AfterCommitFailure;
use CloudCreativity\Modules\Testing\FakeExceptionReporter;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class UnitOfWorkManagerTest extends TestCase
{
    private MockObject&UnitOfWork $unitOfWork;

    private FakeExceptionReporter $reporter;

    private UnitOfWorkManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new UnitOfWorkManager(
            $this->unitOfWork = $this->createMock(UnitOfWork::class),
            $this->reporter = new FakeExceptionReporter(),
        );
    }

    protected function tearDown(): void
    {
        unset($this->manager, $this->unitOfWork, $this->reporter);
        parent::tearDown();
    }

    #[Test]
    public function it_executes_callbacks(): void
    {
        $sequence = [];

        $before1 = function () use (&$sequence): void {
            $this->assertSame(['start'], $sequence);
            $sequence[] = 'before1';
        };

        $before2 = function () use (&$sequence): void {
            $this->assertSame(['start', 'before1'], $sequence);
            $sequence[] = 'before2';
        };

        $after1 = function () use (&$sequence): void {
            $this->assertSame(['start', 'before1', 'before2', 'commit'], $sequence);
            $sequence[] = 'after1';
        };

        $after2 = function () use (&$sequence): void {
            $this->assertSame(['start', 'before1', 'before2', 'commit', 'after1'], $sequence);
            $sequence[] = 'after2';
        };

        $expected = new \DateTime();

        $this->unitOfWork
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (Closure $callback, int $attempts) use (&$sequence) {
                $this->assertSame(1, $attempts);
                $sequence[] = 'start';
                $result = $callback();
                $sequence[] = 'commit';
                return $result;
            });

        $actual = $this->manager->execute(function () use ($before1, $before2, $after1, $after2, $expected) {
            $this->manager->beforeCommit($before1);
            $this->manager->afterCommit($after1);
            $this->manager->beforeCommit($before2);
            $this->manager->afterCommit($after2);
            return $expected;
        }, 2);

        $this->assertSame(['start', 'before1', 'before2', 'commit', 'after1', 'after2'], $sequence);
        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_executes_callback_when_transaction_fails_to_commit(): void
    {
        $expected = new RuntimeException('Boom');
        $sequence = [];
        $actual = null;

        $before1 = function () use (&$sequence): void {
            $this->assertSame(['start'], $sequence);
            $sequence[] = 'before1';
        };

        $before2 = function () use (&$sequence): void {
            $this->assertSame(['start', 'before1'], $sequence);
            $sequence[] = 'before2';
        };

        $after = function (): void {
            $this->fail('Not expecting after callback to be executed.');
        };

        $this->unitOfWork
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (Closure $callback, int $attempts) use (&$sequence, $expected) {
                $this->assertSame(1, $attempts);
                $sequence[] = 'start';
                $callback();
                throw $expected;
            });

        try {
            $this->manager->execute(function () use ($before1, $before2, $after) {
                $this->manager->beforeCommit($before1);
                $this->manager->afterCommit($after);
                $this->manager->beforeCommit($before2);
            });
        } catch (RuntimeException $ex) {
            $actual = $ex;
        }

        $this->assertSame($expected, $actual);
        $this->assertSame(['start', 'before1', 'before2'], $sequence);
    }

    /**
     * If any callbacks get registered by other callbacks, they are executed.
     */
    #[Test]
    public function it_executes_additional_commit_callbacks(): void
    {
        $sequence = [];

        $before = [
            function () use (&$sequence): void {
                $this->assertSame(['start'], $sequence);
                $sequence[] = 'before1';
            },
            function () use (&$sequence): void {
                $this->assertSame(['start', 'before1'], $sequence);
                $sequence[] = 'before2';
            },
            function () use (&$sequence): void {
                $this->assertSame(['start', 'before1', 'before2'], $sequence);
                $sequence[] = 'before3';
            },
        ];

        $after = [
            function () use (&$sequence): void {
                $this->assertSame(['start', 'before1', 'before2', 'before3', 'commit'], $sequence);
                $sequence[] = 'after1';
            },
            function () use (&$sequence): void {
                $this->assertSame(['start', 'before1', 'before2', 'before3', 'commit', 'after1'], $sequence);
                $sequence[] = 'after2';
            },
            function () use (&$sequence): void {
                $this->assertSame(
                    ['start', 'before1', 'before2', 'before3', 'commit', 'after1', 'after2'],
                    $sequence,
                );
                $sequence[] = 'after3';
            },
        ];

        $expected = new \DateTime();

        $this->unitOfWork
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (Closure $callback, int $attempts) use (&$sequence) {
                $this->assertSame(1, $attempts);
                $sequence[] = 'start';
                $result = $callback();
                $sequence[] = 'commit';
                return $result;
            });

        $actual = $this->manager->execute(function () use ($before, $after, $expected) {
            $this->manager->beforeCommit($before[0]);
            $this->manager->beforeCommit($before[1]);
            $this->manager->afterCommit($after[0]);
            $this->manager->beforeCommit(function () use ($before, $after) {
                $this->manager->beforeCommit($before[2]);
                $this->manager->afterCommit($after[1]);
            });
            $this->manager->afterCommit(function () use ($after) {
                $this->manager->afterCommit($after[2]);
            });
            return $expected;
        }, 2);

        $this->assertSame([
            'start',
            'before1',
            'before2',
            'before3',
            'commit',
            'after1',
            'after2',
            'after3',
        ], $sequence);
        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_flushes_callbacks_on_successful_transaction(): void
    {
        $this->unitOfWork
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(fn (Closure $callback) => $callback());

        $this->manager->execute(function () {
            $this->manager->beforeCommit(fn () => null);
            $this->manager->afterCommit(fn () => null);
        });

        // this would error if the manager is not empty at this point (tests below prove that).
        $actual = $this->manager->execute(fn () => 2);

        $this->assertSame(2, $actual);
    }

    #[Test]
    public function it_flushes_callbacks_on_unsuccessful_transaction(): void
    {
        $ex = new LogicException('Boom');
        $count = 0;

        $this->unitOfWork
            ->expects($this->exactly(4))
            ->method('execute')
            ->willReturnCallback(function (\Closure $callback) use ($ex, &$count) {
                $count++;
                $result = $callback();
                if ($count < 4) {
                    throw $ex;
                }
                return $result;
            });

        try {
            $this->manager->execute(function () {
                $this->manager->beforeCommit(fn () => null);
                $this->manager->afterCommit(fn () => null);
            }, 2);
        } catch (LogicException) {
            // expected
        }

        // this would error if the manager is not empty at this point (tests below prove that).
        $actual = $this->manager->execute(fn () => true, 2);

        $this->assertTrue($actual);
        $this->assertSame([$ex, $ex], $this->reporter->reported); // only "swallowed" exceptions are reported
    }

    /**
     * Scenario in which the callback (not the unit of work) errors, with re-attempts.
     *
     * In this scenario, no before or after commit callbacks should be executed. This is
     * because the callback is executed (and throws) before the inner unit of work is
     * committed. Therefore, no before or after callbacks can be executed.
     *
     * However, in this scenario any callbacks that were registered before the exception
     * is thrown should be forgotten. Otherwise, if the callback is retried, the before
     * callbacks will be executed twice.
     */
    #[Test]
    public function it_handles_callback_executing_multiple_times(): void
    {
        $ex = new LogicException('Boom!');
        $sequence = [];
        $count = 0;

        $before = [
            function () use (&$sequence): void {
                $sequence[] = 'before1';
            },
            function () use (&$sequence): void {
                $sequence[] = 'before2';
            },
            function () use (&$sequence): void {
                $sequence[] = 'before3';
            },
        ];

        $after = [
            function () use (&$sequence): void {
                $sequence[] = 'after1';
            },
            function () use (&$sequence): void {
                $sequence[] = 'after2';
            },
        ];

        $this->unitOfWork
            ->expects($this->exactly(3))
            ->method('execute')
            ->willReturnCallback(function (Closure $closure) use (&$sequence): mixed {
                try {
                    $sequence[] = 'begin';
                    $result = $closure();
                    $sequence[] = 'committed';
                    return $result;
                } catch (\Throwable $ex) {
                    $sequence[] = 'rollback';
                    throw $ex;
                }
            });

        $this->manager->execute(function () use ($before, $after, $ex, &$count): void {
            $count++;
            $this->manager->afterCommit($after[0]);
            $this->manager->beforeCommit($before[0]);
            $this->manager->beforeCommit($before[1]);

            if ($count < 3) {
                throw $ex;
            }

            $this->manager->beforeCommit($before[2]);
            $this->manager->afterCommit($after[1]);
        }, 3);

        $this->assertSame([
            'begin',
            'rollback',
            'begin',
            'rollback',
            'begin',
            'before1',
            'before2',
            'before3',
            'committed',
            'after1',
            'after2',
        ], $sequence);
        $this->assertSame([$ex, $ex], $this->reporter->reported);
    }

    /**
     * Scenario where the inner unit of work fails several times.
     *
     * In this scenario, any before callbacks will be executed multiple times. This
     * is because the manager executes them before the inner unit of work commits
     * (and throws). Therefore, the before callbacks are executed again when the
     * callback is retried and is successful.
     *
     * However, after commit callbacks should not be executed until the successful
     * commit of the inner transaction. They should not be executed more than once,
     * because any that are registered on one of the failed attempts should havbe been
     * forgotten.
     */
    #[Test]
    public function it_handles_unit_of_work_failing_multiple_times(): void
    {
        $ex = new LogicException('Boom!');
        $sequence = [];
        $count = 0;

        $before = [
            function () use (&$sequence): void {
                $sequence[] = 'before1';
            },
            function () use (&$sequence): void {
                $sequence[] = 'before2';
            },
        ];

        $after = [
            function () use (&$sequence): void {
                $sequence[] = 'after1';
            },
            function () use (&$sequence): void {
                $sequence[] = 'after2';
            },
        ];

        $this->unitOfWork
            ->expects($this->exactly(3))
            ->method('execute')
            ->willReturnCallback(function (Closure $closure) use ($ex, &$sequence, &$count): mixed {
                $count++;

                $sequence[] = 'begin';
                $result = $closure();

                if ($count < 3) {
                    $sequence[] = 'rollback';
                    throw $ex;
                }

                $sequence[] = 'committed';
                return $result;
            });

        $this->manager->execute(function () use ($before, $after): void {
            $this->manager->afterCommit($after[0]);
            $this->manager->beforeCommit($before[0]);
            $this->manager->beforeCommit($before[1]);
            $this->manager->afterCommit($after[1]);
        }, 3);

        $this->assertSame([
            'begin',
            'before1',
            'before2',
            'rollback',
            'begin',
            'before1',
            'before2',
            'rollback',
            'begin',
            'before1',
            'before2',
            'committed',
            'after1',
            'after2',
        ], $sequence);
        $this->assertSame([$ex, $ex], $this->reporter->reported);
    }

    /**
     * Scenario where an after commit callback fails.
     *
     * The unit of work has already been committed at this point, so the callback must not be
     * retried. The remaining after commit callbacks must still be executed, and the exception
     * from the after commit callback must be wrapped, so that calling code knows the unit of
     * work committed.
     */
    #[Test]
    public function it_does_not_retry_when_after_commit_callback_fails(): void
    {
        $ex = new LogicException('Boom!');
        $sequence = [];
        $actual = null;

        $this->unitOfWork
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (Closure $closure) use (&$sequence): mixed {
                $sequence[] = 'begin';
                $result = $closure();
                $sequence[] = 'committed';
                return $result;
            });

        try {
            $this->manager->execute(function () use ($ex, &$sequence): void {
                $sequence[] = 'callback';
                $this->manager->afterCommit(function () use ($ex, &$sequence): void {
                    $sequence[] = 'after1';
                    throw $ex;
                });
                $this->manager->afterCommit(function () use (&$sequence): void {
                    $sequence[] = 'after2';
                });
            }, 3);
        } catch (AfterCommitCallbacksFailed $e) {
            $actual = $e;
        }

        $this->assertSame($ex, $this->assertSoleAfterCommitFailure($actual));
        $this->assertSame(['begin', 'callback', 'committed', 'after1', 'after2'], $sequence);
        $this->assertSame([], $this->reporter->reported);
    }

    #[Test]
    public function it_executes_all_after_commit_callbacks_when_several_fail(): void
    {
        $ex1 = new LogicException('Boom 1!');
        $ex2 = new RuntimeException('Boom 2!');
        $sequence = [];
        $actual = null;

        $this->unitOfWork
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(fn (Closure $closure): mixed => $closure());

        try {
            $this->manager->execute(function () use ($ex1, $ex2, &$sequence): void {
                $this->manager->afterCommit(function () use ($ex1, &$sequence): void {
                    $sequence[] = 'after1';
                    $this->manager->afterCommit(function () use (&$sequence): void {
                        $sequence[] = 'after4';
                    });
                    throw $ex1;
                });
                $this->manager->afterCommit(function () use (&$sequence): void {
                    $sequence[] = 'after2';
                });
                $this->manager->afterCommit(function () use ($ex2, &$sequence): void {
                    $sequence[] = 'after3';
                    throw $ex2;
                });
            }, 3);
        } catch (AfterCommitCallbacksFailed $e) {
            $actual = $e;
        }

        $this->assertInstanceOf(AfterCommitCallbacksFailed::class, $actual);
        $this->assertSame('2 after commit callbacks failed.', $actual->getMessage());
        $this->assertSame([$ex1, $ex2], $actual->exceptions());
        $this->assertSame($ex1, $actual->getPrevious());
        $this->assertSame(['after1', 'after2', 'after3', 'after4'], $sequence);
        $this->assertSame([], $this->reporter->reported);
    }

    /**
     * Scenario where the first attempt fails before commit, the second attempt commits,
     * and then an after commit callback fails.
     */
    #[Test]
    public function it_does_not_retry_after_commit_following_a_failed_attempt(): void
    {
        $first = new RuntimeException('First attempt failed.');
        $ex = new LogicException('After commit failed.');
        $sequence = [];
        $count = 0;
        $actual = null;

        $this->unitOfWork
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (Closure $closure) use (&$sequence): mixed {
                try {
                    $sequence[] = 'begin';
                    $result = $closure();
                    $sequence[] = 'committed';
                    return $result;
                } catch (\Throwable $ex) {
                    $sequence[] = 'rollback';
                    throw $ex;
                }
            });

        try {
            $this->manager->execute(function () use ($first, $ex, &$sequence, &$count): void {
                $count++;
                $this->manager->afterCommit(function () use ($ex, &$sequence): void {
                    $sequence[] = 'after1';
                    throw $ex;
                });
                if ($count === 1) {
                    throw $first;
                }
            }, 3);
        } catch (AfterCommitCallbacksFailed $e) {
            $actual = $e;
        }

        $this->assertSame($ex, $this->assertSoleAfterCommitFailure($actual));
        $this->assertSame(['begin', 'rollback', 'begin', 'committed', 'after1'], $sequence);
        $this->assertSame([$first], $this->reporter->reported);
    }

    #[Test]
    public function it_flushes_callbacks_when_after_commit_callback_fails(): void
    {
        $this->unitOfWork
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(fn (Closure $callback) => $callback());

        try {
            $this->manager->execute(function () {
                $this->manager->afterCommit(fn () => throw new LogicException('Boom!'));
            }, 2);
        } catch (AfterCommitCallbacksFailed) {
            // expected
        }

        // this would error if the manager is not empty at this point.
        $actual = $this->manager->execute(fn () => 2);

        $this->assertSame(2, $actual);
    }

    #[Test]
    public function it_fails_if_before_callbacks_are_queued_before_transaction(): void
    {
        $this->unitOfWork
            ->expects($this->never())
            ->method('execute');

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Cannot queue a before commit callback when not executing a unit of work.');

        $this->manager->beforeCommit(fn () => null);
    }

    #[Test]
    public function it_fails_if_after_callbacks_are_queued_before_transaction(): void
    {
        $this->unitOfWork
            ->expects($this->never())
            ->method('execute');

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Cannot queue an after commit callback when not executing a unit of work.');

        $this->manager->afterCommit(fn () => null);
    }

    #[Test]
    public function it_cannot_start_transaction_within_an_existing_one(): void
    {
        $this->unitOfWork
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(fn (Closure $callback) => $callback());

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage(
            'Not expecting unit of work manager to start a unit of work within an existing one.',
        );

        $this->manager->execute(function () {
            $this->manager->execute(fn () => null);
        });
    }

    #[Test]
    public function it_fails_if_after_commit_callback_registers_before_commit_callback(): void
    {
        $actual = null;

        $this->unitOfWork
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(fn (Closure $callback) => $callback());

        try {
            $this->manager->execute(function () {
                $this->manager->afterCommit(function () {
                    $this->manager->beforeCommit(fn () => null);
                });
            });
        } catch (AfterCommitCallbacksFailed $ex) {
            $actual = $ex;
        }

        $previous = $this->assertSoleAfterCommitFailure($actual);
        $this->assertInstanceOf(ApplicationException::class, $previous);
        $this->assertSame(
            'Cannot queue a before commit callback when not executing a unit of work.',
            $previous->getMessage(),
        );
    }

    #[Test]
    public function it_fails_if_attempts_is_not_greater_than_zero(): void
    {
        /**
         * Simulates an infinite loop if the zero attempts is not rejected.
         */
        $this->unitOfWork
            ->expects($this->never()) // ensure we do not attempt it once
            ->method('execute')
            ->willReturnCallback(fn (Closure $callback) => $callback());

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Attempts must be greater than zero.');

        /** @phpstan-ignore-next-line */
        $this->manager->execute(fn () => throw new LogicException('Boom!'), 0);
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
        $sequence = [];
        $this->willExecuteUnitOfWork(1, $sequence);

        $this->manager->execute(function () use (&$sequence): void {
            $this->manager->afterCommit(function () use (&$sequence): void {
                $sequence[] = 'after1:start';
                $this->manager->afterCommit(function () use (&$sequence): void {
                    $sequence[] = 'after3';
                });
                $sequence[] = 'after1:end';
            });
            $this->manager->afterCommit(function () use (&$sequence): void {
                $sequence[] = 'after2';
            });
        });

        $this->assertSame(['begin', 'commit', 'after1:start', 'after1:end', 'after2', 'after3'], $sequence);
    }

    #[Test]
    public function it_queues_an_after_commit_callback_registered_by_a_queued_callback(): void
    {
        $sequence = [];
        $this->willExecuteUnitOfWork(1, $sequence);

        $this->manager->execute(function () use (&$sequence): void {
            $this->manager->afterCommit(function () use (&$sequence): void {
                $sequence[] = 'after1:start';
                $this->manager->afterCommit(function () use (&$sequence): void {
                    $sequence[] = 'after2:start';
                    $this->manager->afterCommit(function () use (&$sequence): void {
                        $sequence[] = 'after3';
                    });
                    $sequence[] = 'after2:end';
                });
                $sequence[] = 'after1:end';
            });
        });

        $this->assertSame([
            'begin',
            'commit',
            'after1:start',
            'after1:end',
            'after2:start',
            'after2:end',
            'after3',
        ], $sequence);
    }

    #[Test]
    public function it_executes_remaining_callbacks_when_a_queued_callback_fails(): void
    {
        $ex = new LogicException('Boom!');
        $sequence = [];
        $actual = null;
        $this->willExecuteUnitOfWork(1, $sequence);

        try {
            $this->manager->execute(function () use ($ex, &$sequence): void {
                $this->manager->afterCommit(function () use ($ex, &$sequence): void {
                    $sequence[] = 'after1';
                    $this->manager->afterCommit(function () use ($ex, &$sequence): void {
                        $sequence[] = 'after3';
                        throw $ex;
                    });
                });
                $this->manager->afterCommit(function () use (&$sequence): void {
                    $sequence[] = 'after2';
                    $this->manager->afterCommit(function () use (&$sequence): void {
                        $sequence[] = 'after4';
                    });
                });
            }, 3);
        } catch (AfterCommitCallbacksFailed $e) {
            $actual = $e;
        }

        $this->assertSame($ex, $this->assertSoleAfterCommitFailure($actual));
        $this->assertSame(['begin', 'commit', 'after1', 'after2', 'after3', 'after4'], $sequence);
        $this->assertSame([], $this->reporter->reported);
    }

    #[Test]
    public function it_collects_the_failure_of_a_queued_callback_with_other_failures(): void
    {
        $ex1 = new LogicException('Boom 1!');
        $ex2 = new RuntimeException('Boom 2!');
        $sequence = [];
        $actual = null;
        $this->willExecuteUnitOfWork(1, $sequence);

        try {
            $this->manager->execute(function () use ($ex1, $ex2, &$sequence): void {
                $this->manager->afterCommit(function () use ($ex2, &$sequence): void {
                    $sequence[] = 'after1';
                    $this->manager->afterCommit(function () use ($ex2, &$sequence): void {
                        $sequence[] = 'after3';
                        throw $ex2;
                    });
                });
                $this->manager->afterCommit(function () use ($ex1, &$sequence): void {
                    $sequence[] = 'after2';
                    throw $ex1;
                });
            });
        } catch (AfterCommitCallbacksFailed $e) {
            $actual = $e;
        }

        $this->assertInstanceOf(AfterCommitCallbacksFailed::class, $actual);
        $this->assertSame([$ex1, $ex2], $actual->exceptions());
        $this->assertSame(['begin', 'commit', 'after1', 'after2', 'after3'], $sequence);
    }

    // -------------------------------------------------------------------------
    // Starting a unit of work from an after commit callback
    // -------------------------------------------------------------------------

    #[Test]
    public function it_allows_an_after_commit_callback_to_start_a_new_unit_of_work(): void
    {
        $sequence = [];
        $nested = null;
        $this->willExecuteUnitOfWork(2, $sequence);

        $actual = $this->manager->execute(function () use (&$sequence, &$nested): string {
            $sequence[] = 'outer';
            $this->manager->afterCommit(function () use (&$sequence, &$nested): void {
                $sequence[] = 'after:start';
                $nested = $this->manager->execute(function () use (&$sequence): string {
                    $sequence[] = 'nested';
                    return 'nested-result';
                });
                $sequence[] = 'after:end';
            });
            return 'outer-result';
        });

        $this->assertSame('outer-result', $actual);
        $this->assertSame('nested-result', $nested);
        $this->assertSame([
            'begin',
            'outer',
            'commit',
            'after:start',
            'begin',
            'nested',
            'commit',
            'after:end',
        ], $sequence);
    }

    /**
     * Regression for the nested unit of work resetting the outer after commit queue.
     */
    #[Test]
    public function it_executes_remaining_after_commit_callbacks_after_a_nested_unit_of_work(): void
    {
        $sequence = [];
        $this->willExecuteUnitOfWork(2, $sequence);

        $this->manager->execute(function () use (&$sequence): void {
            $this->manager->afterCommit(function () use (&$sequence): void {
                $sequence[] = 'after1';
                $this->manager->execute(function () use (&$sequence): void {
                    $sequence[] = 'nested';
                });
            });
            $this->manager->afterCommit(function () use (&$sequence): void {
                $sequence[] = 'after2';
            });
            $this->manager->afterCommit(function () use (&$sequence): void {
                $sequence[] = 'after3';
            });
        });

        $this->assertSame([
            'begin',
            'commit',
            'after1',
            'begin',
            'nested',
            'commit',
            'after2',
            'after3',
        ], $sequence);
    }

    /**
     * The nested unit of work executes its own before commit callbacks, but its after commit callbacks
     * are added to the end of the queue being drained. So the nested unit of work returns before they
     * are executed.
     */
    #[Test]
    public function it_executes_the_commit_callbacks_of_a_nested_unit_of_work(): void
    {
        $sequence = [];
        $this->willExecuteUnitOfWork(2, $sequence);

        $this->manager->execute(function () use (&$sequence): void {
            $sequence[] = 'outer';
            $this->manager->beforeCommit(function () use (&$sequence): void {
                $sequence[] = 'outer:before';
            });
            $this->manager->afterCommit(function () use (&$sequence): void {
                $sequence[] = 'outer:after1:start';
                $this->manager->execute(function () use (&$sequence): void {
                    $sequence[] = 'nested';
                    $this->manager->afterCommit(function () use (&$sequence): void {
                        $sequence[] = 'nested:after';
                    });
                    $this->manager->beforeCommit(function () use (&$sequence): void {
                        $sequence[] = 'nested:before';
                    });
                });
                $sequence[] = 'outer:after1:end';
            });
            $this->manager->afterCommit(function () use (&$sequence): void {
                $sequence[] = 'outer:after2';
            });
        });

        $this->assertSame([
            'begin',
            'outer',
            'outer:before',
            'commit',
            'outer:after1:start',
            'begin',
            'nested',
            'nested:before',
            'commit',
            'outer:after1:end',
            'outer:after2',
            'nested:after',
        ], $sequence);
    }

    #[Test]
    public function it_queues_an_after_commit_callback_registered_after_a_nested_unit_of_work(): void
    {
        $sequence = [];
        $this->willExecuteUnitOfWork(2, $sequence);

        $this->manager->execute(function () use (&$sequence): void {
            $this->manager->afterCommit(function () use (&$sequence): void {
                $sequence[] = 'after1:start';
                $this->manager->execute(function () use (&$sequence): void {
                    $sequence[] = 'nested';
                    $this->manager->afterCommit(function () use (&$sequence): void {
                        $sequence[] = 'nested:after';
                    });
                });
                $this->manager->afterCommit(function () use (&$sequence): void {
                    $sequence[] = 'after3';
                });
                $sequence[] = 'after1:end';
            });
            $this->manager->afterCommit(function () use (&$sequence): void {
                $sequence[] = 'after2';
            });
        });

        $this->assertSame([
            'begin',
            'commit',
            'after1:start',
            'begin',
            'nested',
            'commit',
            'after1:end',
            'after2',
            'nested:after',
            'after3',
        ], $sequence);
    }

    #[Test]
    public function it_allows_a_nested_after_commit_callback_to_start_another_unit_of_work(): void
    {
        $sequence = [];
        $this->willExecuteUnitOfWork(3, $sequence);

        $this->manager->execute(function () use (&$sequence): void {
            $sequence[] = 'outer';
            $this->manager->afterCommit(function () use (&$sequence): void {
                $this->manager->execute(function () use (&$sequence): void {
                    $sequence[] = 'nested1';
                    $this->manager->afterCommit(function () use (&$sequence): void {
                        $this->manager->execute(function () use (&$sequence): void {
                            $sequence[] = 'nested2';
                        });
                    });
                    $this->manager->afterCommit(function () use (&$sequence): void {
                        $sequence[] = 'nested1:after';
                    });
                });
            });
            $this->manager->afterCommit(function () use (&$sequence): void {
                $sequence[] = 'outer:after';
            });
        });

        $this->assertSame([
            'begin',
            'outer',
            'commit',
            'begin',
            'nested1',
            'commit',
            'outer:after',
            'begin',
            'nested2',
            'commit',
            'nested1:after',
        ], $sequence);
    }

    #[Test]
    public function it_allows_a_nested_unit_of_work_to_be_retried(): void
    {
        $ex = new LogicException('Boom!');
        $sequence = [];
        $count = 0;
        $this->willExecuteUnitOfWork(3, $sequence);

        $this->manager->execute(function () use ($ex, &$sequence, &$count): void {
            $this->manager->afterCommit(function () use ($ex, &$sequence, &$count): void {
                $this->manager->execute(function () use ($ex, &$sequence, &$count): void {
                    $count++;
                    $sequence[] = 'nested:' . $count;
                    $this->manager->beforeCommit(function () use (&$sequence, $count): void {
                        $sequence[] = 'nested:before:' . $count;
                    });
                    $this->manager->afterCommit(function () use (&$sequence, $count): void {
                        $sequence[] = 'nested:after:' . $count;
                    });
                    if ($count === 1) {
                        throw $ex;
                    }
                }, 2);
            });
            $this->manager->afterCommit(function () use (&$sequence): void {
                $sequence[] = 'outer:after';
            });
        });

        $this->assertSame([
            'begin',
            'commit',
            'begin',
            'nested:1',
            'rollback',
            'begin',
            'nested:2',
            'nested:before:2',
            'commit',
            'outer:after',
            'nested:after:2',
        ], $sequence);
        $this->assertSame([$ex], $this->reporter->reported);
    }

    #[Test]
    public function it_executes_remaining_after_commit_callbacks_when_a_nested_unit_of_work_fails(): void
    {
        $ex = new LogicException('Boom!');
        $sequence = [];
        $actual = null;
        $this->willExecuteUnitOfWork(2, $sequence);

        try {
            $this->manager->execute(function () use ($ex, &$sequence): void {
                $this->manager->afterCommit(function () use ($ex, &$sequence): void {
                    $sequence[] = 'after1';
                    $this->manager->execute(function () use ($ex, &$sequence): void {
                        $sequence[] = 'nested';
                        $this->manager->beforeCommit(function () use (&$sequence): void {
                            $sequence[] = 'nested:before';
                        });
                        $this->manager->afterCommit(function () use (&$sequence): void {
                            $sequence[] = 'nested:after';
                        });
                        throw $ex;
                    });
                });
                $this->manager->afterCommit(function () use (&$sequence): void {
                    $sequence[] = 'after2';
                });
            });
        } catch (AfterCommitCallbacksFailed $e) {
            $actual = $e;
        }

        $this->assertSame($ex, $this->assertSoleAfterCommitFailure($actual));
        $this->assertSame(['begin', 'commit', 'after1', 'begin', 'nested', 'rollback', 'after2'], $sequence);
        $this->assertSame([], $this->reporter->reported);
    }

    /**
     * As the nested after commit callbacks are queued, their failure is thrown by the outer unit of work,
     * not the nested one.
     */
    #[Test]
    public function it_executes_remaining_after_commit_callbacks_when_a_nested_after_commit_callback_fails(): void
    {
        $ex = new LogicException('Boom!');
        $sequence = [];
        $actual = null;
        $this->willExecuteUnitOfWork(2, $sequence);

        try {
            $this->manager->execute(function () use ($ex, &$sequence): void {
                $this->manager->afterCommit(function () use ($ex, &$sequence): void {
                    $sequence[] = $this->manager->execute(function () use ($ex, &$sequence): string {
                        $sequence[] = 'nested';
                        $this->manager->afterCommit(function () use ($ex, &$sequence): void {
                            $sequence[] = 'nested:after1';
                            throw $ex;
                        });
                        $this->manager->afterCommit(function () use (&$sequence): void {
                            $sequence[] = 'nested:after2';
                        });
                        return 'nested:result';
                    });
                });
                $this->manager->afterCommit(function () use (&$sequence): void {
                    $sequence[] = 'outer:after';
                });
            });
        } catch (AfterCommitCallbacksFailed $e) {
            $actual = $e;
        }

        $this->assertSame($ex, $this->assertSoleAfterCommitFailure($actual));
        $this->assertSame([
            'begin',
            'commit',
            'begin',
            'nested',
            'commit',
            'nested:result',
            'outer:after',
            'nested:after1',
            'nested:after2',
        ], $sequence);
        $this->assertSame([], $this->reporter->reported);
    }

    #[Test]
    public function it_cannot_start_a_unit_of_work_within_a_nested_unit_of_work(): void
    {
        $sequence = [];
        $actual = null;
        $this->willExecuteUnitOfWork(2, $sequence);

        try {
            $this->manager->execute(function (): void {
                $this->manager->afterCommit(function (): void {
                    $this->manager->execute(function (): void {
                        $this->manager->execute(fn () => null);
                    });
                });
            });
        } catch (AfterCommitCallbacksFailed $ex) {
            $actual = $ex;
        }

        $previous = $this->assertSoleAfterCommitFailure($actual);
        $this->assertInstanceOf(ApplicationException::class, $previous);
        $this->assertSame(
            'Not expecting unit of work manager to start a unit of work within an existing one.',
            $previous->getMessage(),
        );
        $this->assertSame(['begin', 'commit', 'begin', 'rollback'], $sequence);
    }

    #[Test]
    public function it_fails_if_after_commit_callback_registers_before_commit_callback_after_a_nested_unit_of_work(): void
    {
        $sequence = [];
        $actual = null;
        $this->willExecuteUnitOfWork(2, $sequence);

        try {
            $this->manager->execute(function (): void {
                $this->manager->afterCommit(function (): void {
                    $this->manager->execute(fn () => null);
                    $this->manager->beforeCommit(fn () => null);
                });
            });
        } catch (AfterCommitCallbacksFailed $ex) {
            $actual = $ex;
        }

        $previous = $this->assertSoleAfterCommitFailure($actual);
        $this->assertInstanceOf(ApplicationException::class, $previous);
        $this->assertSame(
            'Cannot queue a before commit callback when not executing a unit of work.',
            $previous->getMessage(),
        );
    }

    // -------------------------------------------------------------------------
    // Resetting state once the unit of work is complete
    // -------------------------------------------------------------------------

    #[Test]
    public function it_is_reset_after_a_nested_unit_of_work(): void
    {
        $sequence = [];
        $this->willExecuteUnitOfWork(3, $sequence);

        $this->manager->execute(function () use (&$sequence): void {
            $this->manager->afterCommit(function () use (&$sequence): void {
                $this->manager->execute(function () use (&$sequence): void {
                    $this->manager->afterCommit(function () use (&$sequence): void {
                        $sequence[] = 'nested:after';
                    });
                });
            });
        });

        $actual = $this->manager->execute(function () use (&$sequence): int {
            $this->manager->beforeCommit(function () use (&$sequence): void {
                $sequence[] = 'second:before';
            });
            $this->manager->afterCommit(function () use (&$sequence): void {
                $sequence[] = 'second:after';
            });
            return 2;
        });

        $this->assertSame(2, $actual);
        $this->assertSame([
            'begin',
            'commit',
            'begin',
            'commit',
            'nested:after',
            'begin',
            'second:before',
            'commit',
            'second:after',
        ], $sequence);
    }

    #[Test]
    public function it_cannot_queue_after_commit_callback_once_the_unit_of_work_has_completed(): void
    {
        $sequence = [];
        $this->willExecuteUnitOfWork(1, $sequence);

        $this->manager->execute(function (): void {
            $this->manager->afterCommit(fn () => null);
        });

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Cannot queue an after commit callback when not executing a unit of work.');

        $this->manager->afterCommit(fn () => null);
    }

    #[Test]
    public function it_cannot_queue_after_commit_callback_once_after_commit_callbacks_have_failed(): void
    {
        $sequence = [];
        $this->willExecuteUnitOfWork(1, $sequence);

        try {
            $this->manager->execute(function (): void {
                $this->manager->afterCommit(fn () => throw new LogicException('Boom!'));
            });
        } catch (AfterCommitCallbacksFailed) {
            // expected
        }

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Cannot queue an after commit callback when not executing a unit of work.');

        $this->manager->afterCommit(fn () => null);
    }

    #[Test]
    public function it_cannot_queue_before_commit_callback_once_the_unit_of_work_has_completed(): void
    {
        $sequence = [];
        $this->willExecuteUnitOfWork(1, $sequence);

        $this->manager->execute(function (): void {
            $this->manager->afterCommit(fn () => null);
        });

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Cannot queue a before commit callback when not executing a unit of work.');

        $this->manager->beforeCommit(fn () => null);
    }

    /**
     * Assert that the after commit callbacks failed with exactly one exception, and return that exception.
     */
    private function assertSoleAfterCommitFailure(?AfterCommitCallbacksFailed $actual): Throwable
    {
        $this->assertInstanceOf(AfterCommitCallbacksFailed::class, $actual);
        $this->assertInstanceOf(AfterCommitFailure::class, $actual);
        $this->assertSame('1 after commit callback failed.', $actual->getMessage());

        $exceptions = $actual->exceptions();

        $this->assertCount(1, $exceptions);
        $this->assertSame($exceptions[0], $actual->getPrevious());

        return $exceptions[0];
    }

    /**
     * Expect the unit of work to be executed the given number of times, recording each execution in the sequence.
     *
     * @param list<string> $sequence
     * @param-out list<string> $sequence
     */
    private function willExecuteUnitOfWork(int $times, array &$sequence): void
    {
        $this->unitOfWork
            ->expects($this->exactly($times))
            ->method('execute')
            ->willReturnCallback(function (Closure $callback, int $attempts) use (&$sequence): mixed {
                $this->assertSame(1, $attempts);
                $sequence[] = 'begin';

                try {
                    $result = $callback();
                } catch (Throwable $ex) {
                    $sequence[] = 'rollback';
                    throw $ex;
                }

                $sequence[] = 'commit';

                return $result;
            });
    }
}
