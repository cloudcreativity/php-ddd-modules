<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Toolkit\Iterables;

use CloudCreativity\Modules\Contracts\Toolkit\Iterables\KeyedSet;
use CloudCreativity\Modules\Toolkit\Iterables\IsKeyedSet;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IsKeyedSetTest extends TestCase
{
    #[Test]
    public function it_iterates_over_the_keys_and_values(): void
    {
        $set = $this->makeSet(['foo' => 'one', 'bar' => 'two']);

        $actual = iterator_to_array($set);

        $this->assertSame(['foo' => 'one', 'bar' => 'two'], $actual);
    }

    #[Test]
    public function it_returns_all_values(): void
    {
        $set = $this->makeSet(['foo' => 'one', 'bar' => 'two']);

        $actual = $set->all();

        $this->assertSame(['foo' => 'one', 'bar' => 'two'], $actual);
    }

    #[Test]
    public function it_counts_the_values(): void
    {
        $set = $this->makeSet(['foo' => 'one', 'bar' => 'two']);

        $this->assertCount(2, $set);
    }

    #[Test]
    public function it_is_not_empty_when_it_has_values(): void
    {
        $set = $this->makeSet(['foo' => 'one']);

        $this->assertFalse($set->isEmpty());
        $this->assertTrue($set->isNotEmpty());
    }

    #[Test]
    public function it_can_be_empty(): void
    {
        $set = $this->makeSet([]);

        $this->assertTrue($set->isEmpty());
        $this->assertFalse($set->isNotEmpty());
        $this->assertCount(0, $set);
        $this->assertSame([], $set->all());
    }

    #[Test]
    public function it_iterates_over_nothing_when_empty(): void
    {
        $set = $this->makeSet([]);

        $actual = iterator_to_array($set);

        $this->assertSame([], $actual);
    }

    /**
     * @param array<string, string> $values
     * @return KeyedSet<string>
     */
    private function makeSet(array $values): KeyedSet
    {
        /**
         * @implements KeyedSet<string>
         */
        return new class ($values) implements KeyedSet {
            /** @use IsKeyedSet<string> */
            use IsKeyedSet;

            /**
             * @param array<string, string> $values
             */
            public function __construct(array $values)
            {
                $this->stack = $values;
            }
        };
    }
}
