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

use CloudCreativity\Modules\Contracts\Toolkit\Result\Error as IError;
use CloudCreativity\Modules\Contracts\Toolkit\Result\ListOfErrors as IListOfErrors;
use CloudCreativity\Modules\Tests\TestBackedEnum;
use CloudCreativity\Modules\Tests\TestUnitEnum;
use CloudCreativity\Modules\Toolkit\Result\Error;
use CloudCreativity\Modules\Toolkit\Result\KeyedSetOfErrors;
use CloudCreativity\Modules\Toolkit\Result\ListOfErrors;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
        $this->assertSame(0, $errors->count());
        $this->assertNull($errors->first());
        $this->assertEmpty($errors->codes());
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

        $this->assertSame($a, $errors->first());
    }

    #[Test]
    public function it_finds_an_error(): void
    {
        $errors = new ListOfErrors(
            new Error(null, 'Message A'),
            new Error(null, 'Message B'),
            $c = new Error(null, 'Message C'),
            new Error(null, 'Message D'),
            $e = new Error(code: TestUnitEnum::Bat),
        );

        $this->assertSame($c, $errors->find(fn (IError $error) => 'Message C' === $error->message()));
        $this->assertSame($e, $errors->find(TestUnitEnum::Bat));
        $this->assertNull($errors->find(fn (IError $error) => 'Message E' === $error->message()));
        $this->assertNull($errors->find(TestUnitEnum::Baz));
    }

    #[Test]
    public function it_checks_if_any_error_matches(): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
            new Error(message: 'Message C'),
            new Error(code: TestUnitEnum::Baz, message: 'Message D'),
        );

        $this->assertTrue($errors->any(fn (IError $error) => 'Message C' === $error->message()));
        $this->assertTrue($errors->any(TestUnitEnum::Baz));
        $this->assertFalse($errors->any(fn (IError $error) => 'Message E' === $error->message()));
        $this->assertFalse($errors->any(TestUnitEnum::Bat));
    }

    #[Test]
    public function it_checks_if_every_error_matches(): void
    {
        $errors1 = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
            new Error(message: 'Message C'),
            new Error(message: 'Message D', code: TestUnitEnum::Baz),
        );

        $errors2 = new ListOfErrors(
            new Error(code: TestUnitEnum::Baz, message: 'Message D'),
            new Error(code: TestUnitEnum::Baz, message: 'Message D'),
            new Error(code: TestUnitEnum::Baz, message: 'Message D'),
        );

        $this->assertTrue($errors1->every(fn (IError $error) => str_starts_with($error->message(), 'Message')));
        $this->assertFalse($errors1->every(fn (IError $error) => null !== $error->code()));
        $this->assertFalse($errors1->every(fn (IError $error) => 'Message A' === $error->message()));

        $this->assertTrue($errors2->every(TestUnitEnum::Baz));
        $this->assertFalse($errors1->every(TestUnitEnum::Baz));
        $this->assertFalse($errors2->every(TestUnitEnum::Bat));
    }

    #[Test]
    public function it_returns_the_sole_error(): void
    {
        $errors1 = new ListOfErrors(
            $expected = new Error(message: 'Message A', code: $code = TestUnitEnum::Baz),
        );

        $errors2 = new ListOfErrors(
            new Error(message: 'Message B'),
            new Error(message: 'Message C'),
            $expected,
        );

        $fn = fn (IError $error) => 'Message A' === $error->message();

        $this->assertSame($expected, $errors1->sole());
        $this->assertSame($expected, $errors1->sole($code));
        $this->assertSame($expected, $errors1->sole($fn));

        $this->assertSame($expected, $errors2->sole($code));
        $this->assertSame($expected, $errors2->sole($fn));
    }

    #[Test]
    public function it_throws_when_there_is_no_sole_error(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected exactly one error but there are 0.');

        $errors = new ListOfErrors();

        $errors->sole();
    }

    #[Test]
    public function it_throws_when_there_are_many_errors_for_sole(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected exactly one error but there are 3.');

        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
            new Error(message: 'Message C'),
        );

        $errors->sole();
    }

    #[Test]
    public function it_throws_when_no_error_matches_the_sole_callback(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected exactly one error matching the criteria but there are 0.');

        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
            new Error(message: 'Message C'),
        );

        $errors->sole(fn (IError $error) => 'Message D' === $error->message());
    }

    #[Test]
    public function it_throws_when_many_errors_match_the_sole_callback(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected exactly one error matching the criteria but there are 2.');

        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
            new Error(message: 'Message A'),
        );

        $errors->sole(fn (IError $error) => 'Message A' === $error->message());
    }

    #[Test]
    public function it_throws_when_no_error_matches_the_sole_code(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected exactly one error with code "Baz" but there are 0.');

        $errors = new ListOfErrors(
            new Error(code: TestUnitEnum::Bat, message: 'Message A'),
            new Error(code: TestUnitEnum::Bat, message: 'Message B'),
            new Error(code: TestUnitEnum::Bat, message: 'Message C'),
        );

        $errors->sole(TestUnitEnum::Baz);
    }

    #[Test]
    public function it_throws_when_many_errors_match_the_sole_code(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected exactly one error with code "Bat" but there are 2.');

        $errors = new ListOfErrors(
            new Error(code: TestUnitEnum::Bat, message: 'Message A'),
            new Error(code: TestUnitEnum::Bat, message: 'Message B'),
            new Error(code: TestUnitEnum::Baz, message: 'Message C'),
        );

        $errors->sole(TestUnitEnum::Bat);
    }

    #[Test]
    public function it_filters_the_list(): void
    {
        $errors = new ListOfErrors(
            new Error(message: 'Message A'),
            $b = new Error(code: TestUnitEnum::Bat, message: 'Message B'),
            $c = new Error(code: TestUnitEnum::Bat, message: 'Message C'),
            $d = new Error(code: TestUnitEnum::Baz, message: 'Message D'),
        );

        $filtered1 = $errors->filter(fn (IError $error) => in_array($error, [$b, $d], true));
        $filtered2 = $errors->filter(TestUnitEnum::Bat);

        $this->assertCount(4, $errors);
        $this->assertSame([$b, $d], $filtered1->all());
        $this->assertSame([$b, $c], $filtered2->filter(TestUnitEnum::Bat)->all());
        $this->assertEmpty($errors->filter(fn (IError $error) => $error->message() === 'Message E'));
        $this->assertEmpty($errors->filter(TestBackedEnum::Bar));
    }

    #[Test]
    public function it_returns_unique_codes(): void
    {
        $errors1 = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(code: TestBackedEnum::Foo, message: 'Message B'),
            new Error(code: TestUnitEnum::Baz, message: 'Message C'),
            new Error(code: TestBackedEnum::Foo, message: 'Message D'),
        );

        $errors2 = new ListOfErrors(
            new Error(message: 'Message E'),
            new Error(message: 'Message F'),
        );

        $this->assertSame([TestBackedEnum::Foo, TestUnitEnum::Baz], $errors1->codes());
        $this->assertEmpty($errors2->codes());
    }

    #[Test]
    public function it_returns_the_first_code(): void
    {
        $errors1 = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(code: TestBackedEnum::Foo, message: 'Message B'),
            new Error(code: TestUnitEnum::Baz, message: 'Message C'),
            new Error(code: TestBackedEnum::Foo, message: 'Message D'),
        );

        $errors2 = new ListOfErrors(
            new Error(message: 'Message E'),
            new Error(message: 'Message F'),
        );

        $this->assertSame(TestBackedEnum::Foo, $errors1->code());
        $this->assertNull($errors2->code());
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

        $this->assertSame(['Message A', 'Message B', 'Message C'], $errors->messages());
    }

    #[Test]
    public function it_returns_the_first_message(): void
    {
        $errors1 = new ListOfErrors(
            new Error(message: 'Message A'),
            new Error(message: 'Message B'),
            new Error(code: TestUnitEnum::Baz),
            new Error(message: 'Message A'),
            new Error(message: 'Message C'),
        );

        $errors2 = new ListOfErrors(
            new Error(code: TestUnitEnum::Baz),
            new Error(code: TestBackedEnum::Foo),
        );

        $this->assertSame('Message A', $errors1->message());
        $this->assertNull($errors2->message());
    }
}
