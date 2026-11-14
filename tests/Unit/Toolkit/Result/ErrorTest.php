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

use CloudCreativity\Modules\Contracts\Toolkit\Result\Error as IError;
use CloudCreativity\Modules\Tests\TestBackedEnum;
use CloudCreativity\Modules\Tests\TestUnitEnum;
use CloudCreativity\Modules\Toolkit\ContractException;
use CloudCreativity\Modules\Toolkit\Result\Error;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ErrorTest extends TestCase
{
    #[Test]
    public function it_has_a_key_message_and_code(): void
    {
        $error = new Error(key: 'foo', message: 'Bar', code: TestUnitEnum::Baz);

        $this->assertInstanceOf(IError::class, $error);
        $this->assertSame('foo', $error->key());
        $this->assertSame('Bar', $error->message());
        $this->assertSame(TestUnitEnum::Baz, $error->code());
        $this->assertTrue($error->is(TestUnitEnum::Baz));
        $this->assertFalse($error->is(TestUnitEnum::Bat));
    }

    #[Test]
    public function it_can_have_only_a_message(): void
    {
        $error = new Error(message: 'Hello World');

        $this->assertNull($error->key());
        $this->assertSame('Hello World', $error->message());
        $this->assertNull($error->code());
    }

    #[Test]
    public function it_can_have_only_a_code(): void
    {
        $error = new Error(code: TestBackedEnum::Foo);

        $this->assertNull($error->key());
        $this->assertSame('', $error->message());
        $this->assertSame(TestBackedEnum::Foo, $error->code());
    }

    #[Test]
    public function it_requires_a_message_or_a_code(): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Error must have a message or a code.');

        new Error(key: 'foo');
    }
}
