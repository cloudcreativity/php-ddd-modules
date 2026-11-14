<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Toolkit\Identifiers;

use CloudCreativity\Modules\Toolkit\Identifiers\IntegerId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IsIdentifierTest extends TestCase
{
    #[Test]
    public function it_is_any_of_the_provided_identifiers_when_one_matches(): void
    {
        $id = new TestIdentifier('foo');

        $actual = $id->any(new TestIdentifier('bar'), null, new TestIdentifier('foo'));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_not_any_of_the_provided_identifiers_when_none_match(): void
    {
        $id = new TestIdentifier('foo');

        $actual = $id->any(new TestIdentifier('bar'), new IntegerId(1), new TestIdentifier('baz'));

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_not_any_of_only_null_identifiers(): void
    {
        $id = new TestIdentifier('foo');

        $actual = $id->any(null, null);

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_not_any_when_no_identifiers_are_provided(): void
    {
        $id = new TestIdentifier('foo');

        $actual = $id->any();

        $this->assertFalse($actual);
    }
}
