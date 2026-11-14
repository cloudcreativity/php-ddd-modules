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
use CloudCreativity\Modules\Toolkit\Result\Meta;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MetaTest extends TestCase
{
    #[Test]
    public function it_holds_values(): void
    {
        $meta = new Meta($values = [
            'foo' => 'bar',
            'baz' => 'bat',
            'foobar' => null,
        ]);

        $this->assertCount(3, $meta);
        $this->assertSame($values, $meta->all());
        $this->assertTrue($meta->isNotEmpty());
        $this->assertFalse($meta->isEmpty());
    }

    /**
     * @return array<string, array{0: string, 1: mixed, 2: mixed}>
     */
    public static function getProvider(): array
    {
        return [
            'value' => ['baz', null, 'bat'],
            'null value with default' => ['foobar', 'default', null],
            'missing key' => ['blah', null, null],
            'missing key with default' => ['blah', 'default', 'default'],
        ];
    }

    #[DataProvider('getProvider')]
    #[Test]
    public function it_gets_a_value(string $key, mixed $default, mixed $expected): void
    {
        $meta = new Meta(['foo' => 'bar', 'baz' => 'bat', 'foobar' => null]);

        $actual = $meta->get($key, $default);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function existsProvider(): array
    {
        return [
            'value' => ['foo', true],
            'null value' => ['foobar', true],
            'missing key' => ['blah', false],
        ];
    }

    #[DataProvider('existsProvider')]
    #[Test]
    public function it_checks_if_a_value_exists(string $key, bool $expected): void
    {
        $meta = new Meta(['foo' => 'bar', 'foobar' => null]);

        $actual = $meta->exists($key);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_is_iterable(): void
    {
        $meta = new Meta($values = [
            'foo' => 'bar',
            'baz' => 'bat',
            'foobar' => null,
        ]);

        $this->assertSame($values, iterator_to_array($meta));
    }

    #[Test]
    public function it_can_be_empty(): void
    {
        $meta = new Meta();

        $this->assertCount(0, $meta);
        $this->assertTrue($meta->isEmpty());
        $this->assertFalse($meta->isNotEmpty());
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function offsetGetProvider(): array
    {
        return [
            'value' => ['baz', 'bat'],
            'null value' => ['foobar', null],
            'missing key' => ['blah', null],
        ];
    }

    #[DataProvider('offsetGetProvider')]
    #[Test]
    public function it_gets_values_via_array_access(string $key, mixed $expected): void
    {
        $meta = new Meta(['foo' => 'bar', 'baz' => 'bat', 'foobar' => null]);

        $actual = $meta[$key];

        $this->assertSame($expected, $actual);
    }

    #[DataProvider('existsProvider')]
    #[Test]
    public function it_checks_if_a_value_is_set_via_array_access(string $key, bool $expected): void
    {
        $meta = new Meta(['foo' => 'bar', 'foobar' => null]);

        $actual = isset($meta[$key]);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_cannot_unset_values(): void
    {
        $meta = new Meta(['foo' => 'bar']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Result meta is immutable.');

        unset($meta['foo']);
    }

    #[Test]
    public function it_cannot_set_values_via_array_access(): void
    {
        $meta = new Meta(['foo' => 'bar']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Result meta is immutable.');

        $meta['foo'] = 'foobar';
    }

    #[Test]
    public function it_puts_a_value_immutably(): void
    {
        $original = new Meta(['foo' => 'bar', 'baz' => 'bat']);

        $actual = $original->put('foo', 'foobar');

        $this->assertNotSame($original, $actual);
        $this->assertSame(['foo' => 'bar', 'baz' => 'bat'], $original->all());
        $this->assertSame(['foo' => 'foobar', 'baz' => 'bat'], $actual->all());
    }

    #[Test]
    public function it_merges_an_array(): void
    {
        $original = new Meta(['foo' => 'bar', 'baz' => 'bat']);

        $actual = $original->merge(['foo' => null, 'foobar' => 'bazbat']);

        $this->assertNotSame($original, $actual);
        $this->assertSame(['foo' => 'bar', 'baz' => 'bat'], $original->all());
        $this->assertSame(['foo' => null, 'baz' => 'bat', 'foobar' => 'bazbat'], $actual->all());
    }

    #[Test]
    public function it_preserves_numeric_string_keys_when_merging(): void
    {
        $original = new Meta(['x' => 'y'])->put('1', 'a');
        $values = new Meta(['x' => 'z'])->put('2', 'b');

        $actual = $original->merge($values);

        $this->assertSame(['x' => 'z', 1 => 'a', 2 => 'b'], $actual->all());
    }

    #[Test]
    public function it_merges_another_meta(): void
    {
        $original = new Meta(['foo' => 'bar', 'baz' => 'bat']);

        $actual = $original->merge(new Meta(['foo' => null, 'foobar' => 'bazbat']));

        $this->assertNotSame($original, $actual);
        $this->assertSame(['foo' => 'bar', 'baz' => 'bat'], $original->all());
        $this->assertSame(['foo' => null, 'baz' => 'bat', 'foobar' => 'bazbat'], $actual->all());
    }

    #[Test]
    public function it_is_a_keyed_set(): void
    {
        $meta = new Meta();

        $this->assertInstanceOf(KeyedSet::class, $meta);
    }

    #[Test]
    public function it_casts_an_array(): void
    {
        $values = ['foo' => 'bar', 'baz' => 'bat'];

        $actual = Meta::cast($values);

        $this->assertEquals(new Meta($values), $actual);
    }

    #[Test]
    public function it_casts_an_empty_array(): void
    {
        $actual = Meta::cast([]);

        $this->assertEquals(new Meta(), $actual);
    }

    #[Test]
    public function it_returns_the_same_instance_when_casting_meta(): void
    {
        $meta = new Meta(['foo' => 'bar']);

        $actual = Meta::cast($meta);

        $this->assertSame($meta, $actual);
    }

    #[Test]
    public function it_puts_a_new_value_immutably(): void
    {
        $original = new Meta(['foo' => 'bar']);

        $actual = $original->put('baz', 'bat');

        $this->assertNotSame($original, $actual);
        $this->assertSame(['foo' => 'bar'], $original->all());
        $this->assertSame(['foo' => 'bar', 'baz' => 'bat'], $actual->all());
    }

    #[Test]
    public function it_puts_a_value_into_empty_meta(): void
    {
        $original = new Meta();

        $actual = $original->put('foo', 'bar');

        $this->assertTrue($original->isEmpty());
        $this->assertSame(['foo' => 'bar'], $actual->all());
    }

    #[Test]
    public function it_merges_an_empty_array(): void
    {
        $original = new Meta(['foo' => 'bar']);

        $actual = $original->merge([]);

        $this->assertNotSame($original, $actual);
        $this->assertSame(['foo' => 'bar'], $actual->all());
    }
}
