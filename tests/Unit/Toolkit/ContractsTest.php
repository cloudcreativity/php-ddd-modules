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
use ReflectionClass;

final class ContractsTest extends TestCase
{
    #[Test]
    public function it_does_not_throw_when_precondition_is_true(): void
    {
        $this->expectNotToPerformAssertions();

        /** @phpstan-ignore-next-line */
        Contracts::assert(true, 'Not expected error.');
    }

    #[Test]
    public function it_does_not_throw_when_precondition_is_true_with_lazy_message(): void
    {
        $this->expectNotToPerformAssertions();

        /** @phpstan-ignore-next-line */
        Contracts::assert(true, fn () => $this->fail('Not expected error.'));
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

    #[Test]
    public function it_throws_with_an_empty_message_by_default(): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessageMatches('/^$/');

        /** @phpstan-ignore-next-line */
        Contracts::assert(false);
    }

    #[Test]
    public function it_cannot_be_instantiated(): void
    {
        $reflection = new ReflectionClass(Contracts::class);

        $this->assertFalse($reflection->isInstantiable());
    }
}
