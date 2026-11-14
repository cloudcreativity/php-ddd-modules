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

use Closure;
use CloudCreativity\Modules\Contracts\Toolkit\Result\Error as IError;
use CloudCreativity\Modules\Contracts\Toolkit\Result\ListOfErrors as IListOfErrors;
use CloudCreativity\Modules\Contracts\Toolkit\Result\Result as IResult;
use CloudCreativity\Modules\Tests\TestBackedEnum;
use CloudCreativity\Modules\Tests\TestUnitEnum;
use CloudCreativity\Modules\Toolkit\Result\Error;
use CloudCreativity\Modules\Toolkit\Result\FailedResultException;
use CloudCreativity\Modules\Toolkit\Result\ListOfErrors;
use CloudCreativity\Modules\Toolkit\Result\Meta;
use CloudCreativity\Modules\Toolkit\Result\Result;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnitEnum;

use function CloudCreativity\Modules\Toolkit\enum_string;

final class ResultTest extends TestCase
{
    /**
     * @return array<string, array{0: (Closure(UnitEnum): non-empty-string)|non-empty-string|null}>
     */
    public static function defaultMessageProvider(): array
    {
        return [
            'no default' => [null],
            'string default' => ['Default message.'],
            'closure default' => [static fn (): never => throw new LogicException('Should not be called.')],
        ];
    }

    // -------------------------------------------------------------------------
    // ok()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_creates_a_successful_result_without_a_value(): void
    {
        $result = Result::ok();

        $this->assertInstanceOf(IResult::class, $result);
        $this->assertNull($result->value());
        $this->assertNull($result->safe());
        $this->assertTrue($result->didSucceed());
        $this->assertFalse($result->didFail());
        $this->assertTrue($result->errors()->isEmpty());
        $this->assertTrue($result->meta()->isEmpty());
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
        $this->assertNull($result->code());
    }

    /**
     * @param (Closure(UnitEnum): non-empty-string)|non-empty-string|null $default
     */
    #[DataProvider('defaultMessageProvider')]
    #[Test]
    public function it_does_not_have_an_error_message_when_successful(Closure|string|null $default): void
    {
        $result = Result::ok();

        $actual = $result->error($default);

        $this->assertNull($actual);
    }

    #[Test]
    public function it_does_not_throw_when_aborting_a_successful_result(): void
    {
        $result = Result::ok();

        $this->expectNotToPerformAssertions();

        $result->abort();
    }

    // -------------------------------------------------------------------------
    // failed() and fail()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_creates_a_failed_result(): void
    {
        $errors = new ListOfErrors(new Error(null, 'Something went wrong.'));

        $result = Result::failed($errors);

        $this->assertFalse($result->didSucceed());
        $this->assertTrue($result->didFail());
        $this->assertNull($result->safe());
        $this->assertSame($errors, $result->errors());
        $this->assertTrue($result->meta()->isEmpty());
        $this->assertNull($result->code());
    }

    /**
     * @param (Closure(UnitEnum): non-empty-string)|non-empty-string|null $default
     */
    #[DataProvider('defaultMessageProvider')]
    #[Test]
    public function it_returns_the_error_message_when_failed(Closure|string|null $default): void
    {
        $result = Result::failed(new Error(null, 'Something went wrong.'));

        $actual = $result->error($default);

        $this->assertSame('Something went wrong.', $actual);
    }

    /**
     * @return array<string, array{0: array<IError>|IError|IListOfErrors|string|UnitEnum}>
     */
    public static function failureProvider(): array
    {
        $error = new Error(null, 'Something went wrong.');

        return [
            'list of errors' => [new ListOfErrors($error)],
            'error' => [$error],
            'string' => ['Something went wrong.'],
            'array of errors' => [[$error]],
            'enum' => [TestBackedEnum::Foo],
        ];
    }

    /**
     * @param array<IError>|IError|IListOfErrors|string|UnitEnum $errors
     */
    #[DataProvider('failureProvider')]
    #[Test]
    public function it_creates_a_failed_result_using_the_fail_alias(
        array|IError|IListOfErrors|string|UnitEnum $errors,
    ): void {
        $actual = Result::fail($errors);

        $this->assertEquals(Result::failed($errors), $actual);
    }

    #[Test]
    public function it_fails_with_a_list_of_errors_implementation(): void
    {
        $errors = $this->createStub(IListOfErrors::class);
        $errors->method('isNotEmpty')->willReturn(true);

        $result = Result::failed($errors);

        $this->assertFalse($result->didSucceed());
        $this->assertTrue($result->didFail());
        $this->assertSame($errors, $result->errors());
    }

    #[Test]
    public function it_fails_with_an_error(): void
    {
        $error = new Error(null, 'Something went wrong.');

        $result = Result::failed($error);

        $this->assertEquals(new ListOfErrors($error), $result->errors());
    }

    #[Test]
    public function it_fails_with_a_string_error(): void
    {
        $result = Result::failed('Something went wrong.');

        $this->assertEquals(new ListOfErrors(new Error(null, 'Something went wrong.')), $result->errors());
    }

    #[Test]
    public function it_fails_with_an_array_of_errors(): void
    {
        $error = new Error(null, 'Something went wrong.');

        $result = Result::failed([$error]);

        $this->assertEquals(new ListOfErrors($error), $result->errors());
    }

    #[Test]
    public function it_fails_with_an_enum_error_code(): void
    {
        $result = Result::failed(TestBackedEnum::Foo);

        $this->assertEquals(new ListOfErrors(new Error(code: TestBackedEnum::Foo)), $result->errors());
    }

    // -------------------------------------------------------------------------
    // abort() and value()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_throws_when_aborting_a_failed_result(): void
    {
        $result = Result::failed('Something went wrong.');

        $this->expectException(FailedResultException::class);
        $this->expectExceptionMessage('Something went wrong.');

        $result->abort();
    }

    #[Test]
    public function it_throws_with_the_result_when_aborting_a_failed_result(): void
    {
        $result = Result::failed('Something went wrong.');
        $actual = null;

        try {
            $result->abort();
        } catch (FailedResultException $ex) {
            $actual = $ex->getResult();
        }

        $this->assertSame($result, $actual);
    }

    #[Test]
    public function it_throws_when_getting_the_value_of_a_failed_result(): void
    {
        $result = Result::failed('Something went wrong.');

        $this->expectException(FailedResultException::class);
        $this->expectExceptionMessage('Something went wrong.');

        $result->value();
    }

    #[Test]
    public function it_throws_with_the_result_when_getting_the_value_of_a_failed_result(): void
    {
        $result = Result::failed('Something went wrong.');
        $actual = null;

        try {
            $result->value();
        } catch (FailedResultException $ex) {
            $actual = $ex->getResult();
        }

        $this->assertSame($result, $actual);
    }

    // -------------------------------------------------------------------------
    // error() and code()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_the_first_message_from_multiple_errors(): void
    {
        $result = Result::failed([
            new Error(code: TestBackedEnum::Foo),
            new Error(code: TestUnitEnum::Baz),
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
        ]);

        $actual = $result->error(static fn (): never => throw new LogicException('Should not be called.'));

        $this->assertSame('Message A', $actual);
    }

    #[Test]
    public function it_returns_the_first_code_from_multiple_errors(): void
    {
        $result = Result::failed([
            new Error(message: 'Message A'),
            new Error(code: TestBackedEnum::Foo),
            new Error(code: TestUnitEnum::Baz),
        ]);

        $actual = $result->code();

        $this->assertSame(TestBackedEnum::Foo, $actual);
    }

    #[Test]
    public function it_returns_null_when_errors_only_have_codes_and_there_is_no_default(): void
    {
        $result = Result::failed([
            new Error(code: TestBackedEnum::Foo),
            new Error(code: TestUnitEnum::Baz),
        ]);

        $actual = $result->error();

        $this->assertNull($actual);
    }

    #[Test]
    public function it_returns_the_default_message_when_errors_only_have_codes(): void
    {
        $result = Result::failed([
            new Error(code: TestBackedEnum::Foo),
            new Error(code: TestUnitEnum::Baz),
        ]);

        $actual = $result->error('Unknown error!');

        $this->assertSame('Unknown error!', $actual);
    }

    #[Test]
    public function it_resolves_the_message_from_the_first_code_when_errors_only_have_codes(): void
    {
        $result = Result::failed([
            new Error(code: TestBackedEnum::Foo),
            new Error(code: TestUnitEnum::Baz),
        ]);
        $received = null;

        $actual = $result->error(function (UnitEnum $code) use (&$received): string {
            $received = $code;
            return enum_string($code);
        });

        $this->assertSame(enum_string(TestBackedEnum::Foo), $actual);
        $this->assertSame(TestBackedEnum::Foo, $received);
    }

    #[Test]
    public function it_throws_when_resolving_a_default_message_without_a_code(): void
    {
        $errors = $this->createStub(IListOfErrors::class);
        $errors->method('isNotEmpty')->willReturn(true);
        $errors->method('message')->willReturn(null);
        $errors->method('code')->willReturn(null);
        $result = Result::failed($errors);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expecting errors to have a code if there is no message.');

        $result->error(fn (UnitEnum $code): string => enum_string($code));
    }

    #[Test]
    public function it_returns_the_default_message_when_errors_have_no_message_or_code(): void
    {
        $errors = $this->createStub(IListOfErrors::class);
        $errors->method('isNotEmpty')->willReturn(true);
        $errors->method('message')->willReturn(null);
        $errors->method('code')->willReturn(null);
        $result = Result::failed($errors);

        $actual = $result->error('Unknown error!');

        $this->assertSame('Unknown error!', $actual);
    }

    // -------------------------------------------------------------------------
    // withMeta()
    // -------------------------------------------------------------------------

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
        $result = Result::ok()->withMeta(['foo' => 'bar']);

        $this->assertEquals(new Meta(['foo' => 'bar']), $result->meta());
    }

    #[Test]
    public function it_merges_meta_values(): void
    {
        $result = Result::ok()->withMeta(['foo' => 'bar', 'baz' => 'bat', 'foobar' => null]);

        $actual = $result->withMeta([
            'baz' => 'blah',
            'foobar' => 'bazbat',
            'bazbat' => 'blah!',
        ]);

        $this->assertNotSame($result, $actual);
        $this->assertSame(['foo' => 'bar', 'baz' => 'bat', 'foobar' => null], $result->meta()->all());
        $this->assertSame([
            'foo' => 'bar',
            'baz' => 'blah',
            'foobar' => 'bazbat',
            'bazbat' => 'blah!',
        ], $actual->meta()->all());
    }

    #[Test]
    public function it_merges_meta_with_a_meta_instance(): void
    {
        $result = Result::ok()->withMeta(['foo' => 'bar', 'baz' => 'bat']);

        $actual = $result->withMeta(new Meta(['baz' => 'blah']));

        $this->assertSame(['foo' => 'bar', 'baz' => 'blah'], $actual->meta()->all());
    }

    #[Test]
    public function it_keeps_the_value_when_setting_meta(): void
    {
        $result = Result::ok(99);

        $actual = $result->withMeta(['foo' => 'bar']);

        $this->assertNotSame($result, $actual);
        $this->assertTrue($actual->didSucceed());
        $this->assertSame(99, $actual->value());
        $this->assertTrue($result->meta()->isEmpty());
    }

    #[Test]
    public function it_keeps_the_errors_when_setting_meta(): void
    {
        $result = Result::failed('Something went wrong.');

        $actual = $result->withMeta(['foo' => 'bar']);

        $this->assertNotSame($result, $actual);
        $this->assertTrue($actual->didFail());
        $this->assertSame($result->errors(), $actual->errors());
        $this->assertTrue($result->meta()->isEmpty());
    }

    // -------------------------------------------------------------------------
    // context()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_context_for_a_successful_result(): void
    {
        $result = Result::ok(99)->withMeta(['foo' => 'bar']);

        $actual = $result->context();

        $this->assertSame(['success' => true, 'value' => 99, 'meta' => ['foo' => 'bar']], $actual);
    }

    #[Test]
    public function it_returns_context_for_a_failed_result(): void
    {
        $result = Result::failed(new Error(code: TestUnitEnum::Baz, message: 'Something went wrong.'));

        $actual = $result->context();

        $this->assertSame([
            'success' => false,
            'error' => ['code' => TestUnitEnum::Baz, 'message' => 'Something went wrong.'],
        ], $actual);
    }
}
