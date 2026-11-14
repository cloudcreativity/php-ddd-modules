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

use ArrayIterator;
use CloudCreativity\Modules\Contracts\Toolkit\Result\Error as IError;
use CloudCreativity\Modules\Contracts\Toolkit\Result\ListOfErrors as IListOfErrors;
use CloudCreativity\Modules\Tests\TestBackedEnum;
use CloudCreativity\Modules\Tests\TestUnitEnum;
use CloudCreativity\Modules\Toolkit\Result\Error;
use CloudCreativity\Modules\Toolkit\Result\KeyedSetOfErrors;
use CloudCreativity\Modules\Toolkit\Result\ListOfErrors;
use Closure;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnitEnum;

final class ListOfErrorsTest extends TestCase
{
    #[Test]
    public function it_holds_errors(): void
    {
        $errors = new ListOfErrors(
            $a = new Error(null, 'Message A'),
            $b = new Error(null, 'Message B'),
        );

        $this->assertInstanceOf(IListOfErrors::class, $errors);
        $this->assertSame([$a, $b], iterator_to_array($errors));
        $this->assertSame([$a, $b], $errors->all());
        $this->assertEquals(new KeyedSetOfErrors($a, $b), $errors->toKeyedSet());
        $this->assertCount(2, $errors);
        $this->assertTrue($errors->isNotEmpty());
        $this->assertFalse($errors->isEmpty());
    }

    #[Test]
    public function it_can_be_empty(): void
    {
        $errors = new ListOfErrors();

        $this->assertTrue($errors->isEmpty());
        $this->assertFalse($errors->isNotEmpty());
        $this->assertCount(0, $errors);
        $this->assertNull($errors->first());
        $this->assertSame([], $errors->codes());
    }

    #[Test]
    public function it_pushes_an_error(): void
    {
        $original = new ListOfErrors(
            $a = new Error(null, 'Message A'),
            $b = new Error(null, 'Message B'),
        );

        $actual = $original->push($c = new Error(null, 'Message C'));

        $this->assertNotSame($original, $actual);
        $this->assertSame([$a, $b], $original->all());
        $this->assertSame([$a, $b, $c], $actual->all());
    }

    #[Test]
    public function it_merges_another_list(): void
    {
        $stack1 = new ListOfErrors(
            $a = new Error(null, 'Message A'),
            $b = new Error(null, 'Message B'),
        );

        $stack2 = new ListOfErrors(
            $c = new Error(null, 'Message C'),
            $d = new Error(null, 'Message D'),
        );

        $actual = $stack1->merge($stack2);

        $this->assertNotSame($stack1, $actual);
        $this->assertNotSame($stack2, $actual);
        $this->assertSame([$a, $b], $stack1->all());
        $this->assertSame([$c, $d], $stack2->all());
        $this->assertSame([$a, $b, $c, $d], $actual->all());
    }

    #[Test]
    public function it_returns_the_first_error(): void
    {
        $errors = new ListOfErrors(
            $a = new Error(null, 'Message A'),
            new Error(null, 'Message B'),
            new Error(null, 'Message C'),
        );

        $actual = $errors->first();

        $this->assertSame($a, $actual);
    }

    /**
     * @return array<string, array{0: Closure(IError): bool|UnitEnum, 1: int|null}>
     */
    public static function findProvider(): array
    {
        return [
            'callback matches' => [static fn (IError $error): bool => 'Message C' === $error->message(), 2],
            'code matches' => [TestUnitEnum::Bat, 4],
            'callback does not match' => [static fn (IError $error): bool => 'Message E' === $error->message(), null],
            'code does not match' => [TestUnitEnum::Baz, null],
        ];
    }

    #[DataProvider('findProvider')]
    #[Test]
    public function it_finds_an_error(Closure|UnitEnum $matcher, ?int $expected): void
    {
        $all = [
            new Error(null, 'Message A'),
            new Error(null, 'Message B'),
            new Error(null, 'Message C'),
            new Error(null, 'Message D'),
            new Error(code: TestUnitEnum::Bat),
        ];
        $errors = new ListOfErrors(...$all);

        $actual = $errors->find($matcher);

        $this->assertSame($expected === null ? null : $all[$expected], $actual);
    }

    /**
     * @return array<string, array{0: Closure(IError): bool|UnitEnum, 1: bool}>
     */
    public static function anyProvider(): array
    {
        return [
            'callback matches' => [static fn (IError $error): bool => 'Message C' === $error->message(), true],
            'code matches' => [TestUnitEnum::Baz, true],
            'callback does not match' => [static fn (IError $error): bool => 'Message E' === $error->message(), false],
            'code does not match' => [TestUnitEnum::Bat, false],
        ];
    }

    #[DataProvider('anyProvider')]
    #[Test]
    public function it_checks_if_any_error_matches(Closure|UnitEnum $matcher, bool $expected): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
            new Error(message: 'Message C'),
            new Error(code: TestUnitEnum::Baz, message: 'Message D'),
        );

        $actual = $errors->any($matcher);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{0: ListOfErrors, 1: Closure(IError): bool|UnitEnum, 2: bool}>
     */
    public static function everyProvider(): array
    {
        $mixed = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
            new Error(message: 'Message C'),
            new Error(message: 'Message D', code: TestUnitEnum::Baz),
        );

        $codes = new ListOfErrors(
            new Error(code: TestUnitEnum::Baz, message: 'Message D'),
            new Error(code: TestUnitEnum::Baz, message: 'Message D'),
            new Error(code: TestUnitEnum::Baz, message: 'Message D'),
        );

        return [
            'callback matches all' => [
                $mixed,
                static fn (IError $error): bool => str_starts_with($error->message(), 'Message'),
                true,
            ],
            'callback matches some' => [
                $mixed,
                static fn (IError $error): bool => null !== $error->code(),
                false,
            ],
            'callback matches one' => [
                $mixed,
                static fn (IError $error): bool => 'Message A' === $error->message(),
                false,
            ],
            'code matches all' => [$codes, TestUnitEnum::Baz, true],
            'code matches some' => [$mixed, TestUnitEnum::Baz, false],
            'code matches none' => [$codes, TestUnitEnum::Bat, false],
        ];
    }

    #[DataProvider('everyProvider')]
    #[Test]
    public function it_checks_if_every_error_matches(
        ListOfErrors $errors,
        Closure|UnitEnum $matcher,
        bool $expected,
    ): void {
        $actual = $errors->every($matcher);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{0: ListOfErrors, 1: Closure(IError): bool|UnitEnum|null, 2: Error}>
     */
    public static function soleProvider(): array
    {
        $expected = new Error(message: 'Message A', code: TestUnitEnum::Baz);
        $one = new ListOfErrors($expected);
        $many = new ListOfErrors(
            new Error(message: 'Message B'),
            new Error(message: 'Message C'),
            $expected,
        );
        $fn = static fn (IError $error): bool => 'Message A' === $error->message();

        return [
            'one error' => [$one, null, $expected],
            'one error matching code' => [$one, TestUnitEnum::Baz, $expected],
            'one error matching callback' => [$one, $fn, $expected],
            'many errors matching code' => [$many, TestUnitEnum::Baz, $expected],
            'many errors matching callback' => [$many, $fn, $expected],
        ];
    }

    #[DataProvider('soleProvider')]
    #[Test]
    public function it_returns_the_sole_error(ListOfErrors $errors, Closure|UnitEnum|null $matcher, Error $expected): void
    {
        $actual = $errors->sole($matcher);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_throws_when_there_is_no_sole_error(): void
    {
        $errors = new ListOfErrors();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected exactly one error but there are 0.');

        $errors->sole();
    }

    #[Test]
    public function it_throws_when_there_are_many_errors_for_sole(): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
            new Error(message: 'Message C'),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected exactly one error but there are 3.');

        $errors->sole();
    }

    #[Test]
    public function it_throws_when_no_error_matches_the_sole_callback(): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
            new Error(message: 'Message C'),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected exactly one error matching the criteria but there are 0.');

        $errors->sole(fn (IError $error) => 'Message D' === $error->message());
    }

    #[Test]
    public function it_throws_when_many_errors_match_the_sole_callback(): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
            new Error(message: 'Message A'),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected exactly one error matching the criteria but there are 2.');

        $errors->sole(fn (IError $error) => 'Message A' === $error->message());
    }

    #[Test]
    public function it_throws_when_no_error_matches_the_sole_code(): void
    {
        $errors = new ListOfErrors(
            new Error(code: TestUnitEnum::Bat, message: 'Message A'),
            new Error(code: TestUnitEnum::Bat, message: 'Message B'),
            new Error(code: TestUnitEnum::Bat, message: 'Message C'),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected exactly one error with code "Baz" but there are 0.');

        $errors->sole(TestUnitEnum::Baz);
    }

    #[Test]
    public function it_throws_when_many_errors_match_the_sole_code(): void
    {
        $errors = new ListOfErrors(
            new Error(code: TestUnitEnum::Bat, message: 'Message A'),
            new Error(code: TestUnitEnum::Bat, message: 'Message B'),
            new Error(code: TestUnitEnum::Baz, message: 'Message C'),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected exactly one error with code "Bat" but there are 2.');

        $errors->sole(TestUnitEnum::Bat);
    }

    #[Test]
    public function it_filters_the_list_using_a_callback(): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            $b = new Error(code: TestUnitEnum::Bat, message: 'Message B'),
            new Error(code: TestUnitEnum::Bat, message: 'Message C'),
            $d = new Error(code: TestUnitEnum::Baz, message: 'Message D'),
        );

        $actual = $errors->filter(fn (IError $error) => in_array($error, [$b, $d], true));

        $this->assertNotSame($errors, $actual);
        $this->assertCount(4, $errors);
        $this->assertSame([$b, $d], $actual->all());
    }

    #[Test]
    public function it_filters_the_list_by_code(): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            $b = new Error(code: TestUnitEnum::Bat, message: 'Message B'),
            $c = new Error(code: TestUnitEnum::Bat, message: 'Message C'),
            new Error(code: TestUnitEnum::Baz, message: 'Message D'),
        );

        $actual = $errors->filter(TestUnitEnum::Bat);

        $this->assertCount(4, $errors);
        $this->assertSame([$b, $c], $actual->all());
    }

    /**
     * @return array<string, array{0: Closure(IError): bool|UnitEnum}>
     */
    public static function noMatchProvider(): array
    {
        return [
            'callback' => [static fn (IError $error): bool => $error->message() === 'Message E'],
            'code' => [TestBackedEnum::Bar],
        ];
    }

    #[DataProvider('noMatchProvider')]
    #[Test]
    public function it_filters_to_an_empty_list_when_no_errors_match(Closure|UnitEnum $matcher): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(code: TestUnitEnum::Bat, message: 'Message B'),
        );

        $actual = $errors->filter($matcher);

        $this->assertTrue($actual->isEmpty());
    }

    #[Test]
    public function it_returns_unique_codes(): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(code: TestBackedEnum::Foo, message: 'Message B'),
            new Error(code: TestUnitEnum::Baz, message: 'Message C'),
            new Error(code: TestBackedEnum::Foo, message: 'Message D'),
        );

        $actual = $errors->codes();

        $this->assertSame([TestBackedEnum::Foo, TestUnitEnum::Baz], $actual);
    }

    #[Test]
    public function it_returns_no_codes_when_errors_do_not_have_codes(): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message E'),
            new Error(message: 'Message F'),
        );

        $actual = $errors->codes();

        $this->assertSame([], $actual);
    }

    #[Test]
    public function it_returns_the_first_code(): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(code: TestBackedEnum::Foo, message: 'Message B'),
            new Error(code: TestUnitEnum::Baz, message: 'Message C'),
            new Error(code: TestBackedEnum::Foo, message: 'Message D'),
        );

        $actual = $errors->code();

        $this->assertSame(TestBackedEnum::Foo, $actual);
    }

    #[Test]
    public function it_returns_no_code_when_errors_do_not_have_codes(): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message E'),
            new Error(message: 'Message F'),
        );

        $actual = $errors->code();

        $this->assertNull($actual);
    }

    #[Test]
    public function it_returns_unique_messages(): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
            new Error(code: TestUnitEnum::Baz),
            new Error(message: 'Message A'),
            new Error(message: 'Message C'),
        );

        $actual = $errors->messages();

        $this->assertSame(['Message A', 'Message B', 'Message C'], $actual);
    }

    #[Test]
    public function it_returns_the_first_message(): void
    {
        $errors = new ListOfErrors(
            new Error(code: TestUnitEnum::Baz),
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
        );

        $actual = $errors->message();

        $this->assertSame('Message A', $actual);
    }

    #[Test]
    public function it_returns_no_message_when_errors_do_not_have_messages(): void
    {
        $errors = new ListOfErrors(
            new Error(code: TestUnitEnum::Baz),
            new Error(code: TestBackedEnum::Foo),
        );

        $actual = $errors->message();

        $this->assertNull($actual);
    }

    // -------------------------------------------------------------------------
    // from()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_the_same_instance_when_created_from_a_list_of_errors(): void
    {
        $errors = new ListOfErrors(new Error(null, 'Message A'));

        $actual = ListOfErrors::from($errors);

        $this->assertSame($errors, $actual);
    }

    #[Test]
    public function it_is_created_from_another_list_of_errors_implementation(): void
    {
        $a = new Error(null, 'Message A');
        $b = new Error(null, 'Message B');
        $other = $this->createStub(IListOfErrors::class);
        $other->method('getIterator')->willReturn(new ArrayIterator([$a, $b]));

        $actual = ListOfErrors::from($other);

        $this->assertEquals(new ListOfErrors($a, $b), $actual);
    }

    #[Test]
    public function it_is_created_from_an_array_of_errors(): void
    {
        $a = new Error(null, 'Message A');
        $b = new Error(null, 'Message B');

        $actual = ListOfErrors::from(['foo' => $a, 'bar' => $b]);

        $this->assertSame([$a, $b], $actual->all());
    }

    #[Test]
    public function it_is_created_from_an_error(): void
    {
        $error = new Error(null, 'Message A');

        $actual = ListOfErrors::from($error);

        $this->assertSame([$error], $actual->all());
    }

    #[Test]
    public function it_is_created_from_a_string(): void
    {
        $actual = ListOfErrors::from('Message A');

        $this->assertEquals(new ListOfErrors(new Error(message: 'Message A')), $actual);
    }

    /**
     * @return array<string, array{0: UnitEnum}>
     */
    public static function codeProvider(): array
    {
        return [
            'unit enum' => [TestUnitEnum::Baz],
            'backed enum' => [TestBackedEnum::Foo],
        ];
    }

    #[DataProvider('codeProvider')]
    #[Test]
    public function it_is_created_from_an_error_code(UnitEnum $code): void
    {
        $actual = ListOfErrors::from($code);

        $this->assertEquals(new ListOfErrors(new Error(code: $code)), $actual);
    }

    #[Test]
    public function it_reindexes_errors_passed_to_the_constructor(): void
    {
        $a = new Error(null, 'Message A');
        $b = new Error(null, 'Message B');

        $actual = new ListOfErrors(...['foo' => $a, 'bar' => $b]);

        $this->assertSame([$a, $b], $actual->all());
    }
}
