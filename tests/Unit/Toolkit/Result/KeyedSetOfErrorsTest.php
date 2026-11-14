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

use CloudCreativity\Modules\Contracts\Toolkit\Iterables\KeyedSet;
use CloudCreativity\Modules\Tests\TestUnitEnum;
use CloudCreativity\Modules\Toolkit\Result\Error;
use CloudCreativity\Modules\Toolkit\Result\KeyedSetOfErrors;
use CloudCreativity\Modules\Toolkit\Result\ListOfErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnitEnum;

final class KeyedSetOfErrorsTest extends TestCase
{
    #[Test]
    public function it_groups_errors_by_key(): void
    {
        $errors = new KeyedSetOfErrors(
            $a = new Error(message: 'Message A', key: 'foo'),
            $b = new Error(message: 'Message B', key: 'bar'),
            $c = new Error(message: 'Message C', key: 'foo'),
            $d = new Error(message: 'Message D'),
            $e = new Error(message: 'Message E'),
            $f = new Error(message: 'Message F', key: TestUnitEnum::Baz),
            $g = new Error(message: 'Message G', key: TestUnitEnum::Bat),
            $h = new Error(message: 'Message H', key: TestUnitEnum::Baz),
        );

        $expected = [
            '_base' => new ListOfErrors($d, $e),
            'bar' => new ListOfErrors($b),
            TestUnitEnum::Bat->name => new ListOfErrors($g),
            TestUnitEnum::Baz->name => new ListOfErrors($f, $h),
            'foo' => new ListOfErrors($a, $c),
        ];

        $this->assertEquals($expected, iterator_to_array($errors));
        $this->assertEquals($expected, $errors->all());
        $this->assertSame(['_base', 'bar', TestUnitEnum::Bat->name, TestUnitEnum::Baz->name, 'foo'], $errors->keys());
        $this->assertEquals(new ListOfErrors($d, $e, $b, $g, $f, $h, $a, $c), $errors->toList());
        $this->assertCount(8, $errors);
        $this->assertTrue($errors->isNotEmpty());
        $this->assertFalse($errors->isEmpty());
    }

    /**
     * @return array<string, array{0: string|UnitEnum, 1: ListOfErrors}>
     */
    public static function getProvider(): array
    {
        return [
            'string key' => ['bar', new ListOfErrors(new Error(message: 'Message B', key: 'bar'))],
            'enum key' => [TestUnitEnum::Baz, new ListOfErrors(
                new Error(message: 'Message C', key: TestUnitEnum::Baz),
                new Error(message: 'Message E', key: TestUnitEnum::Baz),
            )],
        ];
    }

    #[DataProvider('getProvider')]
    #[Test]
    public function it_gets_errors_by_key(string|UnitEnum $key, ListOfErrors $expected): void
    {
        $errors = new KeyedSetOfErrors(
            new Error(message: 'Message A', key: 'foo'),
            new Error(message: 'Message B', key: 'bar'),
            new Error(message: 'Message C', key: TestUnitEnum::Baz),
            new Error(message: 'Message D', key: TestUnitEnum::Bat),
            new Error(message: 'Message E', key: TestUnitEnum::Baz),
            new Error(message: 'Message F'),
        );

        $actual = $errors->get($key);

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function it_can_be_empty(): void
    {
        $errors = new KeyedSetOfErrors();

        $this->assertTrue($errors->isEmpty());
        $this->assertFalse($errors->isNotEmpty());
        $this->assertCount(0, $errors);
    }

    #[Test]
    public function it_puts_an_error_with_a_new_key(): void
    {
        $original = new KeyedSetOfErrors(
            $a = new Error(message: 'Message A', key: 'foo'),
            $b = new Error(message: 'Message B', key: 'bar'),
            $c = new Error(message: 'Message C', key: 'foo'),
        );

        $actual = $original->put($d = new Error(message: 'Message D', key: 'baz'));

        $this->assertNotSame($original, $actual);
        $this->assertEquals([
            'foo' => new ListOfErrors($a, $c),
            'bar' => new ListOfErrors($b),
        ], iterator_to_array($original));
        $this->assertEquals([
            'foo' => new ListOfErrors($a, $c),
            'bar' => new ListOfErrors($b),
            'baz' => new ListOfErrors($d),
        ], iterator_to_array($actual));
        $this->assertSame(['bar', 'baz', 'foo'], $actual->keys());
    }

    #[Test]
    public function it_puts_an_error_with_an_existing_key(): void
    {
        $original = new KeyedSetOfErrors(
            $a = new Error(message: 'Message A', key: 'foo'),
            $b = new Error(message: 'Message B', key: 'bar'),
            $c = new Error(message: 'Message C', key: 'foo'),
        );

        $actual = $original->put($d = new Error(message: 'Message D', key: 'bar'));

        $this->assertNotSame($original, $actual);
        $this->assertEquals([
            'foo' => new ListOfErrors($a, $c),
            'bar' => new ListOfErrors($b),
        ], iterator_to_array($original));
        $this->assertEquals([
            'foo' => new ListOfErrors($a, $c),
            'bar' => new ListOfErrors($b, $d),
        ], iterator_to_array($actual));
        $this->assertSame(['bar', 'foo'], $actual->keys());
    }

    #[Test]
    public function it_puts_an_unkeyed_error_when_there_are_no_base_errors(): void
    {
        $original = new KeyedSetOfErrors(
            $a = new Error(message: 'Message A', key: 'foo'),
            $b = new Error(message: 'Message B', key: 'bar'),
            $c = new Error(message: 'Message C', key: 'foo'),
        );

        $actual = $original->put($d = new Error(message: 'Message D'));

        $this->assertNotSame($original, $actual);
        $this->assertEquals([
            'foo' => new ListOfErrors($a, $c),
            'bar' => new ListOfErrors($b),
        ], iterator_to_array($original));
        $this->assertEquals([
            '_base' => new ListOfErrors($d),
            'foo' => new ListOfErrors($a, $c),
            'bar' => new ListOfErrors($b),
        ], iterator_to_array($actual));
        $this->assertSame(['_base', 'bar', 'foo'], $actual->keys());
    }

    #[Test]
    public function it_puts_an_unkeyed_error_when_there_are_base_errors(): void
    {
        $original = new KeyedSetOfErrors(
            $a = new Error(message: 'Message A'),
            $b = new Error(message: 'Message B', key: 'foo'),
            $c = new Error(message: 'Message C'),
        );

        $actual = $original->put($d = new Error(message: 'Message D'));

        $this->assertNotSame($original, $actual);
        $this->assertEquals([
            '_base' => new ListOfErrors($a, $c),
            'foo' => new ListOfErrors($b),
        ], iterator_to_array($original));
        $this->assertEquals([
            '_base' => new ListOfErrors($a, $c, $d),
            'foo' => new ListOfErrors($b),
        ], iterator_to_array($actual));
        $this->assertSame(['_base', 'foo'], $actual->keys());
    }

    #[Test]
    public function it_merges_another_set(): void
    {
        $set1 = new KeyedSetOfErrors(
            $a = new Error(message: 'Message A', key: 'foo'),
            $b = new Error(message: 'Message B', key: 'bar'),
            $c = new Error(message: 'Message C', key: 'foo'),
        );

        $set2 = new KeyedSetOfErrors(
            $d = new Error(message: 'Message D', key: 'bar'),
            $e = new Error(message: 'Message E', key: 'baz'),
            $f = new Error(message: 'Message F', key: 'bar'),
            $g = new Error(message: 'Message G'),
        );

        $actual = $set1->merge($set2);

        $this->assertNotSame($set1, $actual);
        $this->assertNotSame($set2, $actual);

        $this->assertEquals([
            'bar' => new ListOfErrors($b),
            'foo' => new ListOfErrors($a, $c),
        ], iterator_to_array($set1));

        $this->assertEquals([
            '_base' => new ListOfErrors($g),
            'bar' => new ListOfErrors($d, $f),
            'baz' => new ListOfErrors($e),
        ], iterator_to_array($set2));

        $this->assertEquals([
            '_base' => new ListOfErrors($g),
            'bar' => new ListOfErrors($b, $d, $f),
            'baz' => new ListOfErrors($e),
            'foo' => new ListOfErrors($a, $c),
        ], iterator_to_array($actual));
        $this->assertSame(['_base', 'bar', 'baz', 'foo'], $actual->keys());
        $this->assertCount($set1->count() + $set2->count(), $actual);
    }

    #[Test]
    public function it_is_a_keyed_set(): void
    {
        $errors = new KeyedSetOfErrors();

        $this->assertInstanceOf(KeyedSet::class, $errors);
    }

    #[Test]
    public function it_sorts_keys_case_insensitively(): void
    {
        $errors = new KeyedSetOfErrors(
            new Error(message: 'Message A', key: 'foo'),
            new Error(message: 'Message B', key: 'Bar'),
            new Error(message: 'Message C', key: 'baz'),
            new Error(message: 'Message D'),
        );

        $actual = $errors->keys();

        $this->assertSame(['_base', 'Bar', 'baz', 'foo'], $actual);
    }

    #[Test]
    public function it_sorts_keys_case_insensitively_when_putting_an_error(): void
    {
        $errors = new KeyedSetOfErrors(new Error(message: 'Message A', key: 'bar'));

        $actual = $errors->put(new Error(message: 'Message B', key: 'Foo'))->keys();

        $this->assertSame(['bar', 'Foo'], $actual);
    }

    #[Test]
    public function it_sorts_keys_case_insensitively_when_merging(): void
    {
        $errors = new KeyedSetOfErrors(new Error(message: 'Message A', key: 'bar'));

        $actual = $errors->merge(new KeyedSetOfErrors(new Error(message: 'Message B', key: 'Foo')))->keys();

        $this->assertSame(['bar', 'Foo'], $actual);
    }

    #[Test]
    public function it_returns_an_empty_list_for_a_key_that_does_not_exist(): void
    {
        $errors = new KeyedSetOfErrors(new Error(message: 'Message A', key: 'foo'));

        $actual = $errors->get('bar');

        $this->assertEquals(new ListOfErrors(), $actual);
    }

    #[Test]
    public function it_returns_no_keys_or_errors_when_empty(): void
    {
        $errors = new KeyedSetOfErrors();

        $this->assertSame([], $errors->keys());
        $this->assertSame([], $errors->all());
        $this->assertEquals(new ListOfErrors(), $errors->toList());
    }

    #[Test]
    public function it_returns_the_same_instance_when_created_from_a_keyed_set(): void
    {
        $errors = new KeyedSetOfErrors(new Error(message: 'Message A', key: 'foo'));

        $actual = KeyedSetOfErrors::from($errors);

        $this->assertSame($errors, $actual);
    }

    #[Test]
    public function it_is_created_from_a_list_of_errors(): void
    {
        $errors = new ListOfErrors(
            $a = new Error(message: 'Message A', key: 'foo'),
            $b = new Error(message: 'Message B'),
            $c = new Error(message: 'Message C', key: 'foo'),
        );

        $actual = KeyedSetOfErrors::from($errors);

        $this->assertEquals(new KeyedSetOfErrors($a, $b, $c), $actual);
    }

    #[Test]
    public function it_is_created_from_an_error(): void
    {
        $error = new Error(message: 'Message A', key: 'foo');

        $actual = KeyedSetOfErrors::from($error);

        $this->assertEquals(new KeyedSetOfErrors($error), $actual);
    }

    #[Test]
    public function it_merges_a_list_of_errors(): void
    {
        $set = new KeyedSetOfErrors(
            $a = new Error(message: 'Message A', key: 'foo'),
            $b = new Error(message: 'Message B', key: 'bar'),
        );

        $actual = $set->merge(new ListOfErrors(
            $c = new Error(message: 'Message C', key: 'foo'),
            $d = new Error(message: 'Message D'),
        ));

        $this->assertNotSame($set, $actual);
        $this->assertEquals([
            'bar' => new ListOfErrors($b),
            'foo' => new ListOfErrors($a),
        ], $set->all());
        $this->assertEquals([
            '_base' => new ListOfErrors($d),
            'bar' => new ListOfErrors($b),
            'foo' => new ListOfErrors($a, $c),
        ], $actual->all());
        $this->assertSame(['_base', 'bar', 'foo'], $actual->keys());
    }
}
