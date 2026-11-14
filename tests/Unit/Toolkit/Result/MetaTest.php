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

use CloudCreativity\Modules\Toolkit\Result\Meta;
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
        $this->assertSame($values['baz'], $meta->get('baz'));
        $this->assertNull($meta->get('foobar', 'default'));
        $this->assertTrue($meta->exists('foobar'));
        $this->assertNull($meta->get('blah'));
        $this->assertFalse($meta->exists('blah'));
        $this->assertSame('default', $meta->get('blah', 'default'));
        $this->assertTrue($meta->isNotEmpty());
        $this->assertFalse($meta->isEmpty());
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

    #[Test]
    public function it_supports_array_access(): void
    {
        $meta = new Meta(['foo' => 'bar', 'baz' => 'bat', 'foobar' => null]);

        $this->assertSame('bat', $meta['baz']);
        $this->assertNull($meta['foobar']);
        $this->assertTrue(isset($meta['foobar']));
        $this->assertFalse(isset($meta['blah']));
    }

    #[Test]
    public function it_cannot_unset_values(): void
    {
        $meta = new Meta(['foo' => 'bar']);

        $this->expectException(\LogicException::class);
        unset($meta['foo']);
    }

    #[Test]
    public function it_cannot_set_values_via_array_access(): void
    {
        $meta = new Meta(['foo' => 'bar']);

        $this->expectException(\LogicException::class);
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
    public function it_merges_another_meta(): void
    {
        $original = new Meta(['foo' => 'bar', 'baz' => 'bat']);
        $actual = $original->merge(new Meta(['foo' => null, 'foobar' => 'bazbat']));

        $this->assertNotSame($original, $actual);
        $this->assertSame(['foo' => 'bar', 'baz' => 'bat'], $original->all());
        $this->assertSame(['foo' => null, 'baz' => 'bat', 'foobar' => 'bazbat'], $actual->all());
    }
}
