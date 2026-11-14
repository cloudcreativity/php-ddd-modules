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

use CloudCreativity\Modules\Bus\Middleware\TearDownAfterDispatch;
use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Contracts\Messaging\Query;
use CloudCreativity\Modules\Toolkit\Result\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TearDownAfterDispatchTest extends TestCase
{
    #[Test]
    public function it_invokes_callback_after_success(): void
    {
        $message = $this->createStub(Command::class);
        $result = Result::ok();
        $sequence = [];

        $middleware = new TearDownAfterDispatch(function () use (&$sequence): void {
            $sequence[] = 'teardown';
        });

        $actual = $middleware($message, function ($in) use ($message, $result, &$sequence): Result {
            $sequence[] = 'next';
            $this->assertSame($message, $in);
            return $result;
        });

        $this->assertSame($result, $actual);
        $this->assertSame(['next', 'teardown'], $sequence);
    }

    #[Test]
    public function it_invokes_callback_after_failure(): void
    {
        $message = $this->createStub(Query::class);
        $result = Result::failed('Something went wrong.');
        $sequence = [];

        $middleware = new TearDownAfterDispatch(function () use (&$sequence): void {
            $sequence[] = 'teardown';
        });

        $actual = $middleware($message, function ($in) use ($message, $result, &$sequence): Result {
            $sequence[] = 'next';
            $this->assertSame($message, $in);
            return $result;
        });

        $this->assertSame($result, $actual);
        $this->assertSame(['next', 'teardown'], $sequence);
    }

    #[Test]
    public function it_invokes_callback_after_exception(): void
    {
        $message = $this->createStub(Command::class);
        $exception = new RuntimeException('Something went wrong.');
        $actual = null;
        $sequence = [];

        $middleware = new TearDownAfterDispatch(function () use (&$sequence): void {
            $sequence[] = 'teardown';
        });

        try {
            $middleware($message, function ($in) use ($message, $exception, &$sequence): Result {
                $sequence[] = 'next';
                $this->assertSame($message, $in);
                throw $exception;
            });
            $this->fail('No exception thrown.');
        } catch (RuntimeException $ex) {
            $actual = $ex;
        }

        $this->assertSame($exception, $actual);
        $this->assertSame(['next', 'teardown'], $sequence);
    }
}
