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

use CloudCreativity\Modules\Contracts\Toolkit\Iterables\LazyList;
use CloudCreativity\Modules\Toolkit\Iterables\IsLazyList;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IsLazyListTest extends TestCase
{
    #[Test]
    public function it_iterates_over_list(): void
    {
        $expected = ['one', 'two', 'three'];

        /**
         * @implements LazyList<string>
         */
        $list = new class (...$expected) implements LazyList {
            /** @use IsLazyList<string> */
            use IsLazyList;

            public function __construct(string ...$values)
            {
                $this->source = function () use ($values) {
                    yield from $values;
                };
            }
        };

        $actual = $list->all();

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_yields_list_from_keyed_set(): void
    {
        $expected = ['one' => 'foo', 'two' => 'bar', 'three' => 'baz'];

        /**
         * @implements LazyList<string>
         */
        $list = new class ($expected) implements LazyList {
            /** @use IsLazyList<string> */
            use IsLazyList;

            /**
             * @param array<string, string> $values
             */
            public function __construct(array $values)
            {
                $this->source = function () use ($values) {
                    yield from $values;
                };
            }
        };

        $actual = $list->all();

        $this->assertSame(['foo', 'bar', 'baz'], $actual);
    }

    #[Test]
    public function it_is_empty_when_there_is_no_source(): void
    {
        /**
         * @implements LazyList<string>
         */
        $list = new class () implements LazyList {
            /** @use IsLazyList<string> */
            use IsLazyList;
        };

        $actual = $list->all();

        $this->assertSame([], $actual);
    }

    #[Test]
    public function it_iterates_over_nothing_when_there_is_no_source(): void
    {
        /**
         * @implements LazyList<string>
         */
        $list = new class () implements LazyList {
            /** @use IsLazyList<string> */
            use IsLazyList;
        };

        $actual = iterator_to_array($list);

        $this->assertSame([], $actual);
    }

    #[Test]
    public function it_invokes_the_source_each_time_it_is_iterated(): void
    {
        /**
         * @implements LazyList<int>
         */
        $list = new class () implements LazyList {
            /** @use IsLazyList<int> */
            use IsLazyList;

            public int $calls = 0;

            public function __construct()
            {
                $this->source = function () {
                    $this->calls++;
                    yield $this->calls;
                };
            }
        };

        $first = $list->all();
        $second = $list->all();

        $this->assertSame([[1], [2]], [$first, $second]);
    }
}
