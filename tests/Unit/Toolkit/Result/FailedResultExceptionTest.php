<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Toolkit\Result;

use CloudCreativity\Modules\Tests\TestUnitEnum;
use CloudCreativity\Modules\Toolkit\Result\FailedResultException;
use CloudCreativity\Modules\Toolkit\Result\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FailedResultExceptionTest extends TestCase
{
    #[Test]
    public function it_fails_with_message(): void
    {
        $result = Result::failed('Something went wrong.')
            ->withMeta(['foo' => 'bar']);

        $exception = new FailedResultException($result);

        $this->assertSame($result, $exception->getResult());
        $this->assertSame('Something went wrong.', $exception->getMessage());
    }

    #[Test]
    public function it_fails_with_error_code(): void
    {
        $result = Result::failed(TestUnitEnum::Bat);

        $exception = new FailedResultException($result);

        $expected = 'Failed with error code: Bat';

        $this->assertSame($result, $exception->getResult());
        $this->assertSame($expected, $exception->getMessage());
    }

    #[Test]
    public function it_has_code_and_previous_exception(): void
    {
        $result = Result::failed('Something went wrong.');
        $previous = new \LogicException('Boom!');

        $exception = new FailedResultException($result, 99, $previous);

        $this->assertSame($result, $exception->getResult());
        $this->assertSame('Something went wrong.', $exception->getMessage());
        $this->assertSame(99, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
