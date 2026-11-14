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
use CloudCreativity\Modules\Toolkit\Identifiers\Guid;
use CloudCreativity\Modules\Toolkit\Identifiers\IntegerId;
use CloudCreativity\Modules\Toolkit\Identifiers\StringId;
use CloudCreativity\Modules\Toolkit\Identifiers\Uuid;
use CloudCreativity\Modules\Toolkit\Identifiers\UuidV4;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class IsUuidTest extends TestCase
{
    private const string UUID = '6dcbad65-ed92-4e60-973b-9ba58a022816';

    private const string OTHER_UUID = '38c7be26-6887-4742-8b6b-7d07b30ca596';

    #[Test]
    public function it_converts_to_a_string(): void
    {
        $uuid = new TestUuid(RamseyUuid::fromString(self::UUID));

        $actual = [
            'toString' => $uuid->toString(),
            'cast' => (string) $uuid,
            'key' => $uuid->key(),
            'context' => $uuid->context(),
            'json' => $uuid->jsonSerialize(),
        ];

        $this->assertSame([
            'toString' => self::UUID,
            'cast' => self::UUID,
            'key' => self::UUID,
            'context' => self::UUID,
            'json' => self::UUID,
        ], $actual);
    }

    #[Test]
    public function it_returns_the_bytes(): void
    {
        $base = RamseyUuid::fromString(self::UUID);
        $uuid = new TestUuid($base);

        $actual = $uuid->getBytes();

        $this->assertSame($base->getBytes(), $actual);
    }

    #[Test]
    public function it_returns_the_base_uuid(): void
    {
        $base = RamseyUuid::fromString(self::UUID);
        $uuid = new TestUuid($base);

        $actual = $uuid->toBase();

        $this->assertSame($base, $actual);
    }

    #[Test]
    public function it_is_the_same_as_another_uuid_class_with_the_same_value(): void
    {
        $uuid = new TestUuid(RamseyUuid::fromString(self::UUID));
        $other = new Uuid(RamseyUuid::fromString(self::UUID));

        $actual = $uuid->is($other);

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_not_the_same_as_a_uuid_with_a_different_value(): void
    {
        $uuid = new TestUuid(RamseyUuid::fromString(self::UUID));
        $other = new TestUuid(RamseyUuid::fromString(self::OTHER_UUID));

        $actual = $uuid->is($other);

        $this->assertFalse($actual);
    }

    /**
     * @return array<string, array{0: Identifier|null}>
     */
    public static function notUuidProvider(): array
    {
        return [
            'null' => [null],
            'integer id' => [new IntegerId(1)],
            'string id' => [new StringId(self::UUID)],
            'guid' => [Guid::fromUuid('SomeType', self::UUID)],
        ];
    }

    #[DataProvider('notUuidProvider')]
    #[Test]
    public function it_is_not_the_same_as_other_identifier_types(?Identifier $other): void
    {
        $uuid = new TestUuid(RamseyUuid::fromString(self::UUID));

        $actual = $uuid->is($other);

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_any_of_the_provided_identifiers(): void
    {
        $uuid = new TestUuid(RamseyUuid::fromString(self::UUID));

        $actual = $uuid->any(null, new StringId(self::UUID), new Uuid(RamseyUuid::fromString(self::UUID)));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_equals_a_uuid_with_the_same_value(): void
    {
        $uuid = new TestUuid(RamseyUuid::fromString(self::UUID));

        $this->assertObjectEquals(new TestUuid(RamseyUuid::fromString(self::UUID)), $uuid);
    }

    #[Test]
    public function it_does_not_equal_a_uuid_with_a_different_value(): void
    {
        $uuid = new TestUuid(RamseyUuid::fromString(self::UUID));

        $this->assertObjectNotEquals(new TestUuid(RamseyUuid::fromString(self::OTHER_UUID)), $uuid);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function compareProvider(): array
    {
        return [
            'equal' => [self::UUID, 0],
            'less than' => ['7dcbad65-ed92-4e60-973b-9ba58a022816', -1],
            'greater than' => ['5dcbad65-ed92-4e60-973b-9ba58a022816', 1],
        ];
    }

    #[DataProvider('compareProvider')]
    #[Test]
    public function it_compares_to_another_uuid(string $other, int $expected): void
    {
        $uuid = new TestUuid(RamseyUuid::fromString(self::UUID));

        $actual = $uuid->compareTo(UuidV4::from($other));

        $this->assertSame($expected, $actual);
    }
}
