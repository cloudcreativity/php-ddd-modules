<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\Bus\Middleware;

use CloudCreativity\Modules\Application\Bus\Middleware\ExecuteInUnitOfWork;
use CloudCreativity\Modules\Contracts\Application\UnitOfWork\UnitOfWorkManager;
use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Toolkit\Result\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

final class ExecuteInUnitOfWorkTest extends TestCase
{
    /**
     * @var array<string>
     */
    private array $sequence = [];

    #[Test]
    public function it_commits_unit_of_work_on_success(): void
    {
        $command = $this->createMock(Command::class);
        $expected = Result::ok();

        $middleware = new ExecuteInUnitOfWork(
            $transactions = $this->createMock(UnitOfWorkManager::class),
            2,
        );

        $transactions
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (\Closure $callback, int $attempts) {
                $this->assertSame(2, $attempts);
                $this->sequence[] = 'begin';
                $result = $callback();
                $this->sequence[] = 'commit';
                return $result;
            });

        $actual = $middleware($command, function ($cmd) use ($command, $expected) {
            $this->assertSame($command, $cmd);
            $this->sequence[] = 'handler';
            return $expected;
        });

        $this->assertSame(['begin', 'handler', 'commit'], $this->sequence);
        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_does_not_commit_unit_of_work_on_failure(): void
    {
        $command = $this->createMock(Command::class);
        $expected = Result::failed('Something went wrong.');

        $middleware = new ExecuteInUnitOfWork(
            $transactions = $this->createMock(UnitOfWorkManager::class),
            2,
        );

        $transactions
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (\Closure $callback, int $attempts) {
                $this->assertSame(2, $attempts);
                $this->sequence[] = 'begin';
                $result = $callback();
                $this->sequence[] = 'commit';
                return $result;
            });

        $actual = $middleware($command, function ($cmd) use ($command, $expected) {
            $this->assertSame($command, $cmd);
            $this->sequence[] = 'handler';
            return $expected;
        });

        $this->assertSame(['begin', 'handler'], $this->sequence);
        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_does_not_catch_exceptions(): void
    {
        $command = $this->createMock(Command::class);
        $expected = new \RuntimeException('Boom! Something went wrong.');

        $middleware = new ExecuteInUnitOfWork(
            $transactions = $this->createMock(UnitOfWorkManager::class),
            2,
        );

        $transactions
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (\Closure $callback, int $attempts) {
                $this->assertSame(2, $attempts);
                $this->sequence[] = 'begin';
                $result = $callback();
                $this->sequence[] = 'commit';
                return $result;
            });

        $actual = null;

        try {
            $middleware($command, function ($cmd) use ($command, $expected): never {
                $this->assertSame($command, $cmd);
                $this->sequence[] = 'handler';
                throw $expected;
            });
        } catch (Throwable $ex) {
            $actual = $ex;
        }

        $this->assertSame(['begin', 'handler'], $this->sequence);
        $this->assertSame($expected, $actual);
    }
}
