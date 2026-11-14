<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Toolkit;

use CloudCreativity\Modules\Toolkit\ContractException;
use CloudCreativity\Modules\Toolkit\Contracts;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContractsTest extends TestCase
{
    #[Test]
    public function it_does_not_throw_when_precondition_is_true(): void
    {
        /** @phpstan-ignore-next-line */
        Contracts::assert(true, 'Not expected error.');

        /** @phpstan-ignore-next-line */
        $this->assertTrue(true);
    }

    #[Test]
    public function it_does_not_throw_when_precondition_is_true_with_lazy_message(): void
    {
        /** @phpstan-ignore-next-line */
        Contracts::assert(true, fn () => $this->fail('Not expected error.'));

        /** @phpstan-ignore-next-line */
        $this->assertTrue(true);
    }

    #[Test]
    public function it_throws_when_precondition_is_false(): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage($expected = 'The expected message.');

        /** @phpstan-ignore-next-line */
        Contracts::assert(false, $expected);
    }

    #[Test]
    public function it_throws_when_precondition_is_false_with_lazy_message(): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage($expected = 'The expected message.');

        /** @phpstan-ignore-next-line */
        Contracts::assert(false, fn () => $expected);
    }
}
