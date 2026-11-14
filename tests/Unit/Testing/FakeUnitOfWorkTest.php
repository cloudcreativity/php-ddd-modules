<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Testing;

use Closure;
use CloudCreativity\Modules\Contracts\Application\Ports\UnitOfWork;
use CloudCreativity\Modules\Testing\FakeExceptionReporter;
use CloudCreativity\Modules\Testing\FakeUnitOfWork;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class FakeUnitOfWorkTest extends TestCase
{
    #[Test]
    public function it_is_a_unit_of_work(): void
    {
        $unitOfWork = new FakeUnitOfWork();

        $this->assertInstanceOf(UnitOfWork::class, $unitOfWork);
    }

    #[Test]
    public function it_exposes_the_provided_exception_reporter(): void
    {
        $reporter = new FakeExceptionReporter();

        $unitOfWork = new FakeUnitOfWork($reporter);

        $this->assertSame($reporter, $unitOfWork->exceptions);
    }

    // -------------------------------------------------------------------------
    // execute()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_is_successful_on_first_attempt(): void
    {
        $unitOfWork = new FakeUnitOfWork();

        $result = $unitOfWork->execute(fn () => 'result');

        $this->assertSame('result', $result);
        $this->assertSame(['attempt:1', 'commit:1'], $unitOfWork->sequence);
        $this->assertSame([], $unitOfWork->exceptions->reported);
        $this->assertSame(1, $unitOfWork->attempts);
        $this->assertSame(1, $unitOfWork->commits);
        $this->assertSame(0, $unitOfWork->rollbacks);
    }

    /**
     * @return array<string, array{int<1, max>}>
     */
    public static function successfulRetryProvider(): array
    {
        return [
            'before max attempts' => [4],
            'on max attempts' => [3],
        ];
    }

    /**
     * @param int<1, max> $attempts
     */
    #[DataProvider('successfulRetryProvider')]
    #[Test]
    public function it_is_successful_after_retrying(int $attempts): void
    {
        $ex1 = new RuntimeException('An error occurred.');
        $ex2 = new RuntimeException('Another error occurred.');
        $unitOfWork = new FakeUnitOfWork();

        $result = $unitOfWork->execute($this->failThenSucceed([$ex1, $ex2]), $attempts);

        $this->assertSame('result', $result);
        $this->assertSame([
            'attempt:1',
            'rollback:1',
            'attempt:2',
            'rollback:2',
            'attempt:3',
            'commit:3',
        ], $unitOfWork->sequence);
        $this->assertSame([$ex1, $ex2], $unitOfWork->exceptions->reported);
        $this->assertSame(3, $unitOfWork->attempts);
        $this->assertSame(1, $unitOfWork->commits);
        $this->assertSame(2, $unitOfWork->rollbacks);
    }

    #[Test]
    public function it_rethrows_the_last_exception_when_not_successful(): void
    {
        $ex1 = new RuntimeException('An error occurred.');
        $ex2 = new RuntimeException('Another error occurred.');
        $unitOfWork = new FakeUnitOfWork();

        $this->expectExceptionObject($ex2);

        $unitOfWork->execute($this->failThenSucceed([$ex1, $ex2]), 2);
    }

    #[Test]
    public function it_rolls_back_every_attempt_when_not_successful(): void
    {
        $ex1 = new RuntimeException('An error occurred.');
        $ex2 = new RuntimeException('Another error occurred.');
        $unitOfWork = new FakeUnitOfWork();

        try {
            $unitOfWork->execute($this->failThenSucceed([$ex1, $ex2]), 2);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame([
            'attempt:1',
            'rollback:1',
            'attempt:2',
            'rollback:2',
        ], $unitOfWork->sequence);
        $this->assertSame([$ex1, $ex2], $unitOfWork->exceptions->reported);
        $this->assertSame(2, $unitOfWork->attempts);
        $this->assertSame(0, $unitOfWork->commits);
        $this->assertSame(2, $unitOfWork->rollbacks);
    }

    #[Test]
    public function it_reports_exceptions_to_the_provided_exception_reporter(): void
    {
        $reporter = new FakeExceptionReporter();
        $ex = new RuntimeException('An error occurred.');
        $unitOfWork = new FakeUnitOfWork($reporter);

        $unitOfWork->execute($this->failThenSucceed([$ex]), 2);

        $this->assertSame([$ex], $reporter->reported);
    }

    #[Test]
    public function it_does_not_retry_when_the_exception_reporter_rethrows(): void
    {
        $reporter = new FakeExceptionReporter();
        $reporter->none();
        $ex = new RuntimeException('An error occurred.');
        $unitOfWork = new FakeUnitOfWork($reporter);
        $actual = null;

        try {
            $unitOfWork->execute(fn () => throw $ex, 3);
        } catch (RuntimeException $caught) {
            $actual = $caught;
        }

        $this->assertSame($ex, $actual);
        $this->assertSame(['attempt:1', 'rollback:1'], $unitOfWork->sequence);
        $this->assertSame(1, $unitOfWork->attempts);
        $this->assertSame(0, $unitOfWork->commits);
        $this->assertSame(1, $unitOfWork->rollbacks);
        $this->assertSame([], $reporter->reported);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidAttemptsProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    #[DataProvider('invalidAttemptsProvider')]
    #[Test]
    public function it_throws_when_attempts_is_less_than_one(int $attempts): void
    {
        $unitOfWork = new FakeUnitOfWork();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The number of attempts must be at least 1.');

        // @phpstan-ignore argument.type (deliberately violates the int<1, max> contract)
        $unitOfWork->execute(fn () => 'result', $attempts);
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
