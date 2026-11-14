<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\Bus;

use CloudCreativity\Modules\Bus\SanitizedMessage;
use CloudCreativity\Modules\Contracts\Messaging\Query;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SanitizedMessageTest extends TestCase
{
    #[Test]
    public function it_iterates_over_public_properties_that_are_not_sensitive(): void
    {
        $sanitized = new SanitizedMessage(new TestSensitiveCommand('Bob', 'p4ssw0rd', 42));

        $actual = [];
        foreach ($sanitized as $key => $value) {
            $actual[$key] = $value;
        }

        $this->assertSame(['name' => 'Bob', 'age' => 42], $actual);
    }

    #[Test]
    public function it_returns_public_properties_that_are_not_sensitive_as_context(): void
    {
        $sanitized = new SanitizedMessage(new TestSensitiveCommand('Bob', 'p4ssw0rd'));

        $actual = $sanitized->context();

        $this->assertSame(['name' => 'Bob', 'age' => null], $actual);
    }

    #[Test]
    public function it_returns_empty_context_for_a_message_without_public_properties(): void
    {
        $sanitized = new SanitizedMessage($this->createStub(Query::class));

        $actual = $sanitized->context();

        $this->assertSame([], $actual);
    }
}
