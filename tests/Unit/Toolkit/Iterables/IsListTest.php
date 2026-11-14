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

use CloudCreativity\Modules\Contracts\Toolkit\Iterables\ListIterator;
use CloudCreativity\Modules\Toolkit\Iterables\IsList;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IsListTest extends TestCase
{
    #[Test]
    public function it_iterates_over_the_values(): void
    {
        $list = $this->makeList('one', 'two', 'three');

        $actual = iterator_to_array($list);

        $this->assertSame([0 => 'one', 1 => 'two', 2 => 'three'], $actual);
    }

    #[Test]
    public function it_returns_all_values(): void
    {
        $list = $this->makeList('one', 'two', 'three');

        $actual = $list->all();

        $this->assertSame(['one', 'two', 'three'], $actual);
    }

    #[Test]
    public function it_counts_the_values(): void
    {
        $list = $this->makeList('one', 'two', 'three');

        $this->assertCount(3, $list);
    }

    #[Test]
    public function it_is_not_empty_when_it_has_values(): void
    {
        $list = $this->makeList('one');

        $this->assertFalse($list->isEmpty());
        $this->assertTrue($list->isNotEmpty());
    }

    #[Test]
    public function it_can_be_empty(): void
    {
        $list = $this->makeList();

        $this->assertTrue($list->isEmpty());
        $this->assertFalse($list->isNotEmpty());
        $this->assertCount(0, $list);
        $this->assertSame([], $list->all());
    }

    #[Test]
    public function it_iterates_over_nothing_when_empty(): void
    {
        $list = $this->makeList();

        $actual = iterator_to_array($list);

        $this->assertSame([], $actual);
    }

    /**
     * @return ListIterator<string>
     */
    private function makeList(string ...$values): ListIterator
    {
        /**
         * @implements ListIterator<string>
         */
        return new class (...$values) implements ListIterator {
            /** @use IsList<string> */
            use IsList;

            public function __construct(string ...$values)
            {
                $this->stack = array_values($values);
            }
        };
    }
}
