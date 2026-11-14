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

use CloudCreativity\Modules\Contracts\Toolkit\Result\ListOfErrors as IListOfErrors;
use CloudCreativity\Modules\Contracts\Toolkit\Result\Result as IResult;
use CloudCreativity\Modules\Tests\TestBackedEnum;
use CloudCreativity\Modules\Tests\TestUnitEnum;
use CloudCreativity\Modules\Toolkit\Result\Error;
use CloudCreativity\Modules\Toolkit\Result\FailedResultException;
use CloudCreativity\Modules\Toolkit\Result\ListOfErrors;
use CloudCreativity\Modules\Toolkit\Result\Meta;
use CloudCreativity\Modules\Toolkit\Result\Result;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnitEnum;

use function CloudCreativity\Modules\Toolkit\enum_string;

final class ResultTest extends TestCase
{
    #[Test]
    public function it_creates_a_successful_result_without_a_value(): void
    {
        $result = Result::ok();
        $result->abort();

        $this->assertInstanceOf(IResult::class, $result);
        $this->assertNull($result->value());
        $this->assertNull($result->safe());
        $this->assertTrue($result->didSucceed());
        $this->assertFalse($result->didFail());
        $this->assertTrue($result->errors()->isEmpty());
        $this->assertTrue($result->meta()->isEmpty());
        $this->assertNull($result->error());
        $this->assertNull($result->error('There is no error!'));
        $this->assertNull($result->error(fn (): never => $this->fail('Should not be called.')));
        $this->assertNull($result->code());
    }

    #[Test]
    public function it_creates_a_successful_result_with_a_value(): void
    {
        $result = Result::ok($value = 99);

        $this->assertSame($value, $result->value());
        $this->assertSame($value, $result->safe());
        $this->assertTrue($result->didSucceed());
        $this->assertFalse($result->didFail());
        $this->assertTrue($result->errors()->isEmpty());
        $this->assertTrue($result->meta()->isEmpty());
        $this->assertNull($result->error());
        $this->assertNull($result->code());
    }

    /**
     * @return Result<null>
     */
    #[Test]
    public function it_creates_a_failed_result(): Result
    {
        $errors = new ListOfErrors(new Error(null, 'Something went wrong.'));
        $result = Result::failed($errors);

        $this->assertFalse($result->didSucceed());
        $this->assertTrue($result->didFail());
        $this->assertNull($result->safe());
        $this->assertSame($errors, $result->errors());
        $this->assertTrue($result->meta()->isEmpty());
        $this->assertSame('Something went wrong.', $result->error());
        $this->assertSame('Something went wrong.', $result->error('Blah!'));
        $this->assertSame('Something went wrong.', $result->error(
            fn (): never => $this->fail('Should not be called.'),
        ));
        $this->assertNull($result->code());
        $this->assertEquals($result, Result::fail($errors));

        return $result;
    }

    /**
     * @param Result<mixed> $result
     */
    #[Depends('it_creates_a_failed_result')]
    #[Test]
    public function it_throws_when_aborting_a_failed_result(Result $result): void
    {
        try {
            $result->abort();
            $this->fail('No exception thrown.');
        } catch (FailedResultException $ex) {
            $this->assertSame($result, $ex->getResult());
        }
    }

    #[Test]
    public function it_returns_the_first_message_and_code_from_multiple_errors(): void
    {
        $errors = new ListOfErrors(
            new Error(code: TestBackedEnum::Foo),
            new Error(code: TestUnitEnum::Baz),
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
        );

        $result = Result::failed($errors);

        $this->assertSame('Message A', $result->error());
        $this->assertSame('Message A', $result->error(fn (): never => $this->fail('Should not be called.')));
        $this->assertSame(TestBackedEnum::Foo, $result->code());
    }

    #[Test]
    public function it_resolves_the_message_when_errors_only_have_codes(): void
    {
        $errors = new ListOfErrors(
            new Error(code: TestBackedEnum::Foo),
            new Error(code: TestUnitEnum::Baz),
        );

        $result = Result::failed($errors);

        $this->assertSame(TestBackedEnum::Foo, $result->code());
        $this->assertNull($result->error());
        $this->assertSame('Unknown error!', $result->error('Unknown error!'));
        $this->assertSame(
            enum_string(TestBackedEnum::Foo),
            $result->error(function (UnitEnum $code): string {
                $this->assertSame(TestBackedEnum::Foo, $code);
                return enum_string($code);
            }),
        );
        $this->assertSame(TestBackedEnum::Foo, $result->code());
    }

    /**
     * @param Result<null> $result
     */
    #[Depends('it_creates_a_failed_result')]
    #[Test]
    public function it_throws_when_getting_value_on_failed_result(Result $result): void
    {
        try {
            $result->value();
            $this->fail('No exception thrown.');
        } catch (FailedResultException $ex) {
            $this->assertSame($result, $ex->getResult());
        }
    }

    #[Test]
    public function it_fails_with_a_list_of_errors_implementation(): void
    {
        $errors = $this->createMock(IListOfErrors::class);
        $errors->method('isNotEmpty')->willReturn(true);
        $result = Result::failed($errors);

        $this->assertFalse($result->didSucceed());
        $this->assertTrue($result->didFail());
        $this->assertSame($errors, $result->errors());
        $this->assertEquals($result, Result::fail($errors));
    }

    #[Test]
    public function it_fails_with_an_error(): void
    {
        $error = new Error(null, 'Something went wrong.');
        $result = Result::failed($error);

        $this->assertEquals(new ListOfErrors($error), $result->errors());
        $this->assertEquals($result, Result::fail($error));
    }

    #[Test]
    public function it_fails_with_a_string_error(): void
    {
        $error = new Error(null, 'Something went wrong.');
        $result = Result::failed($error->message());

        $this->assertEquals(new ListOfErrors($error), $result->errors());
        $this->assertEquals($result, Result::fail($error->message()));
    }

    #[Test]
    public function it_fails_with_an_array_of_errors(): void
    {
        $error = new Error(null, 'Something went wrong.');
        $result = Result::failed([$error]);

        $this->assertEquals(new ListOfErrors($error), $result->errors());
        $this->assertEquals($result, Result::fail([$error]));
    }

    #[Test]
    public function it_fails_with_an_enum_error_code(): void
    {
        $error = new Error(code: $code = TestBackedEnum::Foo);
        $result = Result::failed($code);

        $this->assertEquals(new ListOfErrors($error), $result->errors());
        $this->assertEquals($result, Result::fail($code));
    }

    #[Test]
    public function it_requires_errors_when_calling_failed(): void
    {
        $this->expectException(\AssertionError::class);
        Result::failed(new ListOfErrors());
    }

    #[Test]
    public function it_requires_errors_when_calling_fail(): void
    {
        $this->expectException(\AssertionError::class);
        Result::fail(new ListOfErrors());
    }

    #[Test]
    public function it_sets_meta(): void
    {
        $meta = new Meta(['foo' => 'bar']);
        $result = Result::ok()->withMeta($meta);

        $this->assertEquals($meta, $result->meta());
    }

    #[Test]
    public function it_sets_meta_from_an_array(): void
    {
        $meta = new Meta(['foo' => 'bar']);
        $result = Result::ok()->withMeta($meta->all());

        $this->assertEquals($meta, $result->meta());
    }

    #[Test]
    public function it_merges_meta_values(): void
    {
        $result1 = Result::ok()
            ->withMeta($values = ['foo' => 'bar', 'baz' => 'bat', 'foobar' => null]);

        $result2 = $result1->withMeta([
            'baz' => 'blah',
            'foobar' => 'bazbat',
            'bazbat' => 'blah!',
        ]);

        $this->assertSame($values, $result1->meta()->all());
        $this->assertSame([
            'foo' => 'bar',
            'baz' => 'blah',
            'foobar' => 'bazbat',
            'bazbat' => 'blah!',
        ], $result2->meta()->all());
    }
}
