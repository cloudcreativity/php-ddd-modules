<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\Bus;

use CloudCreativity\Modules\Application\Bus\AbortOnFailureException;
use CloudCreativity\Modules\Contracts\Toolkit\Result\FailedResultException;
use CloudCreativity\Modules\Toolkit\Result\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AbortOnFailureExceptionTest extends TestCase
{
    #[Test]
    public function it_is_a_failed_result_exception(): void
    {
        $ex = new AbortOnFailureException(Result::failed('Something went wrong.'));

        $this->assertInstanceOf(RuntimeException::class, $ex);
        $this->assertInstanceOf(FailedResultException::class, $ex);
    }

    #[Test]
    public function it_returns_the_result(): void
    {
        $result = Result::failed('Something went wrong.');
        $ex = new AbortOnFailureException($result);

        $actual = $ex->getResult();

        $this->assertSame($result, $actual);
    }
}
