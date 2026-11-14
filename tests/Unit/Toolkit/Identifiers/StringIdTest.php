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

use CloudCreativity\Modules\Contracts\Toolkit\Identifiers\Identifier;
use CloudCreativity\Modules\Toolkit\ContractException;
use CloudCreativity\Modules\Toolkit\Identifiers\Guid;
use CloudCreativity\Modules\Toolkit\Identifiers\IntegerId;
use CloudCreativity\Modules\Toolkit\Identifiers\StringId;
use CloudCreativity\Modules\Toolkit\Identifiers\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StringIdTest extends TestCase
{
    #[Test]
    public function it_wraps_a_string(): void
    {
        $id = new StringId('99');

        $this->assertSame('99', $id->value);
        $this->assertSame('99', $id->context());
        $this->assertSame('99', $id->key());
        $this->assertSame('99', $id->toString());
        $this->assertSame('99', (string) $id);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['id' => '99'], \JSON_THROW_ON_ERROR),
            json_encode(compact('id'), \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function it_can_be_zero(): void
    {
        $id = new StringId('0');
        $this->assertSame('0', $id->value);
    }

    #[Test]
    public function it_must_not_be_empty(): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Identifier value must be a non-empty string.');
        new StringId('');
    }

    #[Test]
    public function it_is_equals(): void
    {
        $this->assertObjectEquals($id = new StringId('99'), $other = StringId::from('99'));
        $this->assertSame($id, StringId::from($id));
        $this->assertTrue($id->is($other));
        $this->assertTrue($id->any(new StringId('foo'), new StringId('bar'), null, $other));
        $this->assertEquals($id, StringId::tryFrom('99'));
        $this->assertSame($id, StringId::tryFrom($id));
    }

    #[Test]
    public function it_is_not_equal(): void
    {
        $id = new StringId('99');
        $this->assertFalse($id->equals($other = new StringId('100')));
        $this->assertFalse($id->is($other));
        $this->assertFalse($id->any(new StringId('foo'), new StringId('bar'), null, $other));
        $this->assertFalse($id->any());
    }

    /**
     * @return array<int, array<Identifier|null>>
     */
    public static function notStringIdProvider(): array
    {
        return [
            [null],
            [new IntegerId(1)],
            [new Guid('SomeType', new StringId('1'))],
            [new Uuid(\Ramsey\Uuid\Uuid::uuid4())],
        ];
    }

    #[DataProvider('notStringIdProvider')]
    #[Test]
    public function it_is_not_equal_to_other_identifier_types(?Identifier $other): void
    {
        $id = new StringId('1');

        $this->assertFalse($id->is($other));
        $this->assertFalse($id->any($other, new StringId('foo'), null));
    }

    #[DataProvider('notStringIdProvider')]
    #[Test]
    public function it_returns_null_when_trying_from_other_identifier_types(?Identifier $other): void
    {
        $this->assertNull(StringId::tryFrom($other));
    }

    #[DataProvider('notStringIdProvider')]
    #[Test]
    public function it_cannot_be_created_from_other_identifier_types(?Identifier $other): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Unexpected identifier type, received: ' . get_debug_type($other));
        StringId::from($other);
    }
}
