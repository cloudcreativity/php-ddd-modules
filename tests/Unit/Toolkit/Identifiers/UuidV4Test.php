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
use CloudCreativity\Modules\Toolkit\Identifiers\UuidV4;
use CloudCreativity\Modules\Toolkit\Identifiers\UuidV7;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Lazy\LazyUuidFromString;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class UuidV4Test extends TestCase
{
    private const string UUID = '6dcbad65-ed92-4e60-973b-9ba58a022816';

    private const string OTHER_UUID = '38c7be26-6887-4742-8b6b-7d07b30ca596';

    #[Test]
    public function it_wraps_a_ramsey_uuid(): void
    {
        $base = RamseyUuid::uuid4();

        $id = UuidV4::from($base);

        $this->assertSame($base, $id->value);
        $this->assertSame($base->toString(), $id->context());
        $this->assertSame($base->toString(), $id->key());
        $this->assertSame($base->toString(), $id->toString());
        $this->assertSame((string) $base, (string) $id);
        $this->assertSame($base->getBytes(), $id->getBytes());
        $this->assertJsonStringEqualsJsonString(
            json_encode(['id' => $base], \JSON_THROW_ON_ERROR),
            json_encode(compact('id'), \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function it_accepts_a_lazy_uuid_v4(): void
    {
        $base = new LazyUuidFromString(RamseyUuid::uuid4()->toString());

        $actual = new UuidV4($base);

        $this->assertSame($base, $actual->value);
    }

    #[Test]
    public function it_rejects_a_lazy_uuid_of_another_version(): void
    {
        $base = new LazyUuidFromString(RamseyUuid::uuid7()->toString());

        $this->expectException(ContractException::class);

        new UuidV4($base);
    }

    #[Test]
    public function it_can_be_compared_for_sorting(): void
    {
        $uuid1 = UuidV4::make();
        $uuid2 = UuidV4::make();
        $uuid3 = UuidV4::make();
        $expected = [$uuid3->value, $uuid1->value, $uuid2->value];
        usort($expected, fn ($a, $b) => $a->compareTo($b));
        $actual = [$uuid2, $uuid1, $uuid3];

        usort($actual, fn ($a, $b) => $a->compareTo($b));

        $this->assertSame($expected, array_map(fn ($id) => $id->value, $actual));
    }

    // -------------------------------------------------------------------------
    // from() and tryFrom()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_the_same_instance_when_created_from_a_uuid_v4(): void
    {
        $id = UuidV4::make();

        $actual = UuidV4::from($id);

        $this->assertSame($id, $actual);
    }

    #[Test]
    public function it_returns_the_same_instance_when_trying_from_a_uuid_v4(): void
    {
        $id = UuidV4::make();

        $actual = UuidV4::tryFrom($id);

        $this->assertSame($id, $actual);
    }

    #[Test]
    public function it_tries_to_create_from_a_ramsey_uuid(): void
    {
        $base = RamseyUuid::uuid4();

        $actual = UuidV4::tryFrom($base);

        $this->assertEquals(UuidV4::from($base), $actual);
    }

    #[Test]
    public function it_can_be_created_from_a_string(): void
    {
        $expected = UuidV4::from(RamseyUuid::fromString(self::UUID));

        $actual = UuidV4::from(self::UUID);

        $this->assertObjectEquals($expected, $actual);
    }

    #[Test]
    public function it_can_try_to_create_from_a_string(): void
    {
        $expected = UuidV4::from(RamseyUuid::fromString(self::UUID));

        $actual = UuidV4::tryFrom(self::UUID);

        $this->assertNotNull($actual);
        $this->assertObjectEquals($expected, $actual);
    }

    #[Test]
    public function it_returns_null_when_trying_from_an_invalid_string(): void
    {
        $actual = UuidV4::tryFrom('invalid');

        $this->assertNull($actual);
    }

    /**
     * @return array<string, array{0: Identifier|null}>
     */
    public static function notUuidV4Provider(): array
    {
        return [
            'null' => [null],
            'integer id' => [new IntegerId(1)],
            'string id' => [new StringId('foo')],
            'guid' => [new Guid('SomeType', new Uuid(RamseyUuid::fromString(self::UUID)))],
            'uuid v7 wrapped in uuid' => [new Uuid(RamseyUuid::uuid7())],
            'uuid v7' => [UuidV7::make()],
        ];
    }

    #[DataProvider('notUuidV4Provider')]
    #[Test]
    public function it_cannot_be_created_from_other_identifier_types(?Identifier $other): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Unexpected identifier type, received: ' . get_debug_type($other));

        UuidV4::from($other);
    }

    #[DataProvider('notUuidV4Provider')]
    #[Test]
    public function it_returns_null_when_trying_from_other_identifier_types(?Identifier $other): void
    {
        $actual = UuidV4::tryFrom($other);

        $this->assertNull($actual);
    }

    // -------------------------------------------------------------------------
    // is(), any() and equals()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_equals_a_uuid_with_the_same_value(): void
    {
        $id = UuidV4::from(self::UUID);

        $this->assertObjectEquals(UuidV4::from(self::UUID), $id);
    }

    #[Test]
    public function it_does_not_equal_a_uuid_with_a_different_value(): void
    {
        $id = UuidV4::from(self::UUID);

        $this->assertObjectNotEquals(UuidV4::from(self::OTHER_UUID), $id);
    }

    #[Test]
    public function it_is_a_uuid_v4_with_the_same_value(): void
    {
        $base = RamseyUuid::uuid4();
        $id = UuidV4::from($base);

        $actual = $id->is(UuidV4::from($base));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_a_uuid_with_the_same_value(): void
    {
        $base = RamseyUuid::uuid4();
        $id = UuidV4::from($base);

        $actual = $id->is(new Uuid($base));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_not_a_uuid_with_a_different_value(): void
    {
        $id = UuidV4::from(self::UUID);

        $actual = $id->is(UuidV4::from(self::OTHER_UUID));

        $this->assertFalse($actual);
    }

    #[DataProvider('notUuidV4Provider')]
    #[Test]
    public function it_is_not_equal_to_other_identifier_types(?Identifier $other): void
    {
        $id = UuidV4::from(self::UUID);

        $actual = $id->is($other);

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_any_of_the_provided_identifiers_when_one_matches(): void
    {
        $base = RamseyUuid::uuid4();
        $id = UuidV4::from($base);

        $actual = $id->any(null, UuidV4::make(), UuidV4::from($base));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_not_any_of_the_provided_identifiers_when_none_match(): void
    {
        $id = UuidV4::from(self::UUID);

        $actual = $id->any(null, UuidV4::make(), UuidV4::from(self::OTHER_UUID));

        $this->assertFalse($actual);
    }

    #[DataProvider('notUuidV4Provider')]
    #[Test]
    public function it_is_not_any_of_other_identifier_types(?Identifier $other): void
    {
        $id = UuidV4::from(self::UUID);

        $actual = $id->any(null, UuidV4::make(), $other);

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_not_any_when_no_identifiers_are_provided(): void
    {
        $id = UuidV4::from(self::UUID);

        $actual = $id->any();

        $this->assertFalse($actual);
    }
}
