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

use CloudCreativity\Modules\Contracts\Toolkit\Result\Result as IResult;
use CloudCreativity\Modules\Tests\TestUnitEnum;
use CloudCreativity\Modules\Toolkit\Result\FailedResultException;
use CloudCreativity\Modules\Toolkit\Result\ListOfErrors;
use CloudCreativity\Modules\Toolkit\Result\Result;
use LogicException;
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

        $this->assertSame($result, $exception->getResult());
        $this->assertSame('Failed with error code: Bat', $exception->getMessage());
    }

    #[Test]
    public function it_has_code_and_previous_exception(): void
    {
        $result = Result::failed('Something went wrong.');
        $previous = new LogicException('Boom!');

        $exception = new FailedResultException($result, 99, $previous);

        $this->assertSame($result, $exception->getResult());
        $this->assertSame('Something went wrong.', $exception->getMessage());
        $this->assertSame(99, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function it_has_an_empty_message_when_the_result_has_no_message_or_code(): void
    {
        $result = $this->createFailedResult();

        $exception = new FailedResultException($result);

        $this->assertSame('', $exception->getMessage());
    }

    #[Test]
    public function it_returns_the_result_context(): void
    {
        $exception = new FailedResultException(
            Result::failed('Something went wrong.')->withMeta(['foo' => 'bar']),
        );

        $actual = $exception->context();

        $this->assertSame([
            'success' => false,
            'error' => ['message' => 'Something went wrong.'],
            'meta' => ['foo' => 'bar'],
        ], $actual);
    }

    #[Test]
    public function it_wraps_a_result_context_that_is_not_an_array(): void
    {
        $exception = new FailedResultException($this->createFailedResult('Some context'));

        $actual = $exception->context();

        $this->assertSame(['Some context'], $actual);
    }

    /**
     * @return IResult<mixed>
     */
    private function createFailedResult(mixed $context = null): IResult
    {
        $result = $this->createStub(IResult::class);
        $result->method('didFail')->willReturn(true);
        $result->method('error')->willReturn(null);
        $result->method('errors')->willReturn(new ListOfErrors());
        $result->method('context')->willReturn($context);

        return $result;
    }
}
