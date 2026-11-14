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

use CloudCreativity\Modules\Contracts\Toolkit\Contextual;
use CloudCreativity\Modules\Tests\TestBackedEnum;
use CloudCreativity\Modules\Tests\TestUnitEnum;
use CloudCreativity\Modules\Toolkit\Identifiers\Guid;
use CloudCreativity\Modules\Toolkit\Identifiers\IntegerId;
use CloudCreativity\Modules\Toolkit\Result\ContextualResult;
use CloudCreativity\Modules\Toolkit\Result\Error;
use CloudCreativity\Modules\Toolkit\Result\Result;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContextualResultTest extends TestCase
{
    #[Test]
    public function it_is_contextual(): void
    {
        $contextual = new ContextualResult(Result::ok());

        $this->assertInstanceOf(Contextual::class, $contextual);
    }

    // -------------------------------------------------------------------------
    // success()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_is_successful_when_the_result_succeeded(): void
    {
        $contextual = new ContextualResult(Result::ok());

        $this->assertTrue($contextual->success());
    }

    #[Test]
    public function it_is_not_successful_when_the_result_failed(): void
    {
        $contextual = new ContextualResult(Result::failed('Something went wrong.'));

        $this->assertFalse($contextual->success());
    }

    // -------------------------------------------------------------------------
    // value()
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{0: bool|float|int}>
     */
    public static function scalarProvider(): array
    {
        return [
            'true' => [true],
            'false' => [false],
            'integer' => [99],
            'zero' => [0],
            'float' => [1.5],
        ];
    }

    #[DataProvider('scalarProvider')]
    #[Test]
    public function it_returns_bool_int_and_float_values(bool|float|int $value): void
    {
        $contextual = new ContextualResult(Result::ok($value));

        $actual = $contextual->value();

        $this->assertSame($value, $actual);
    }

    #[Test]
    public function it_returns_the_context_of_a_contextual_value(): void
    {
        $value = Guid::fromInteger('SomeType', 123);
        $contextual = new ContextualResult(Result::ok($value));

        $actual = $contextual->value();

        $this->assertSame(['type' => 'SomeType', 'id' => 123], $actual);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function omittedValueProvider(): array
    {
        return [
            'null' => [null],
            'string' => ['secret'],
            'array' => [['foo' => 'bar']],
            'object' => [new stdClass()],
            'enum' => [TestBackedEnum::Foo],
        ];
    }

    #[DataProvider('omittedValueProvider')]
    #[Test]
    public function it_does_not_return_other_values(mixed $value): void
    {
        $contextual = new ContextualResult(Result::ok($value));

        $actual = $contextual->value();

        $this->assertNull($actual);
    }

    #[Test]
    public function it_does_not_return_a_value_for_a_failed_result(): void
    {
        $contextual = new ContextualResult(Result::failed('Something went wrong.'));

        $actual = $contextual->value();

        $this->assertNull($actual);
    }

    // -------------------------------------------------------------------------
    // meta()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_the_meta(): void
    {
        $contextual = new ContextualResult(Result::ok()->withMeta(['foo' => 'bar', 'baz' => null]));

        $actual = $contextual->meta();

        $this->assertSame(['foo' => 'bar', 'baz' => null], $actual);
    }

    #[Test]
    public function it_returns_null_when_the_meta_is_empty(): void
    {
        $contextual = new ContextualResult(Result::ok());

        $actual = $contextual->meta();

        $this->assertNull($actual);
    }

    // -------------------------------------------------------------------------
    // errors()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_the_errors(): void
    {
        $contextual = new ContextualResult(Result::failed([
            new Error(code: TestUnitEnum::Baz, message: 'Message A', key: 'foo'),
            new Error(message: 'Message B'),
            new Error(code: TestBackedEnum::Foo),
        ]));

        $actual = $contextual->errors();

        $this->assertSame([
            ['code' => TestUnitEnum::Baz, 'key' => 'foo', 'message' => 'Message A'],
            ['message' => 'Message B'],
            ['code' => TestBackedEnum::Foo],
        ], $actual);
    }

    #[Test]
    public function it_returns_no_errors_for_a_successful_result(): void
    {
        $contextual = new ContextualResult(Result::ok());

        $actual = $contextual->errors();

        $this->assertSame([], $actual);
    }

    // -------------------------------------------------------------------------
    // context()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_context_for_a_successful_result_without_a_value(): void
    {
        $contextual = new ContextualResult(Result::ok());

        $actual = $contextual->context();

        $this->assertSame(['success' => true], $actual);
    }

    #[Test]
    public function it_returns_context_for_a_successful_result_with_a_value_and_meta(): void
    {
        $contextual = new ContextualResult(
            Result::ok(new IntegerId(99))->withMeta(['foo' => 'bar']),
        );

        $actual = $contextual->context();

        $this->assertSame([
            'success' => true,
            'value' => 99,
            'meta' => ['foo' => 'bar'],
        ], $actual);
    }

    #[Test]
    public function it_keeps_a_false_value_in_the_context(): void
    {
        $contextual = new ContextualResult(Result::ok(false));

        $actual = $contextual->context();

        $this->assertSame(['success' => true, 'value' => false], $actual);
    }

    #[Test]
    public function it_returns_context_for_a_failed_result_with_one_error(): void
    {
        $contextual = new ContextualResult(
            Result::failed(new Error(message: 'Something went wrong.', key: 'foo'))
                ->withMeta(['foo' => 'bar']),
        );

        $actual = $contextual->context();

        $this->assertSame([
            'success' => false,
            'error' => ['key' => 'foo', 'message' => 'Something went wrong.'],
            'meta' => ['foo' => 'bar'],
        ], $actual);
    }

    #[Test]
    public function it_returns_context_for_a_failed_result_with_many_errors(): void
    {
        $contextual = new ContextualResult(Result::failed([
            new Error(message: 'Message A'),
            new Error(code: TestUnitEnum::Bat),
        ]));

        $actual = $contextual->context();

        $this->assertSame([
            'success' => false,
            'errors' => [
                ['message' => 'Message A'],
                ['code' => TestUnitEnum::Bat],
            ],
        ], $actual);
    }
}
