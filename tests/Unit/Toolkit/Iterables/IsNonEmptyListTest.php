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

use CloudCreativity\Modules\Contracts\Toolkit\Iterables\NonEmptyList;
use CloudCreativity\Modules\Toolkit\Iterables\IsNonEmptyList;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IsNonEmptyListTest extends TestCase
{
    #[Test]
    public function it_iterates_over_the_values(): void
    {
        $list = $this->makeList('one', 'two', 'three');

        $actual = iterator_to_array($list);

        $this->assertSame(['one', 'two', 'three'], $actual);
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

        $actual = count($list);

        $this->assertSame(3, $actual);
    }

    /**
     * @return NonEmptyList<string>
     */
    private function makeList(string $value, string ...$values): NonEmptyList
    {
        /**
         * @implements NonEmptyList<string>
         */
        return new class ($value, ...$values) implements NonEmptyList {
            /** @use IsNonEmptyList<string> */
            use IsNonEmptyList;

            public function __construct(string $value, string ...$values)
            {
                $this->stack = [$value, ...array_values($values)];
            }
        };
    }
}
