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
use CloudCreativity\Modules\Testing\FakeExceptionReporter;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
     * from the after commit callback must be thrown as-is.
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
        } catch (LogicException $e) {
            $actual = $e;
        }

        $this->assertSame($ex, $actual);
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
        } catch (LogicException $e) {
            $actual = $e;
        }

        $this->assertSame($ex, $actual);
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
        } catch (LogicException) {
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
        $this->unitOfWork
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(fn (Closure $callback) => $callback());

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Cannot queue a before commit callback as unit of work has been committed.');

        $this->manager->execute(function () {
            $this->manager->afterCommit(function () {
                $this->manager->beforeCommit(fn () => null);
            });
        });
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
}
