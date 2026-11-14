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
    }

    #[Test]
    public function it_is_its_code(): void
    {
        $error = new Error(code: TestUnitEnum::Baz);

        $actual = $error->is(TestUnitEnum::Baz);

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_not_another_code(): void
    {
        $error = new Error(code: TestUnitEnum::Baz);

        $actual = $error->is(TestUnitEnum::Bat);

        $this->assertFalse($actual);
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

    #[Test]
    public function it_can_have_an_enum_key(): void
    {
        $error = new Error(message: 'Hello World', key: TestUnitEnum::Bat);

        $this->assertSame(TestUnitEnum::Bat, $error->key());
    }

    #[Test]
    public function it_converts_an_empty_key_to_null(): void
    {
        $error = new Error(message: 'Hello World', key: '');

        $this->assertNull($error->key());
    }

    #[Test]
    public function it_can_have_a_message_of_zero(): void
    {
        $error = new Error(message: '0');

        $this->assertSame('0', $error->message());
    }

    #[Test]
    public function it_can_have_a_key_of_zero(): void
    {
        $error = new Error(message: 'Hello World', key: '0');

        $this->assertSame('0', $error->key());
    }

    #[Test]
    public function it_is_not_a_code_when_it_has_no_code(): void
    {
        $error = new Error(message: 'Hello World');

        $actual = $error->is(TestUnitEnum::Baz);

        $this->assertFalse($actual);
    }
}
