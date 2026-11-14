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
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Lazy\LazyUuidFromString;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class UuidV7Test extends TestCase
{
    private const string UUID = '019b7acc-aff8-7f70-adc9-e9c7f632e6df';

    private const string OTHER_UUID = '019b7acd-0f6f-7828-a1cf-94c34a239594';

    #[Test]
    public function it_wraps_a_ramsey_uuid(): void
    {
        $base = RamseyUuid::uuid7();

        $id = UuidV7::from($base);

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
    public function it_accepts_a_lazy_uuid_v7(): void
    {
        $base = new LazyUuidFromString(RamseyUuid::uuid7()->toString());

        $actual = new UuidV7($base);

        $this->assertSame($base, $actual->value);
    }

    #[Test]
    public function it_rejects_a_lazy_uuid_of_another_version(): void
    {
        $base = new LazyUuidFromString(RamseyUuid::uuid4()->toString());

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Expecting a version 7 UUID.');

        new UuidV7($base);
    }

    #[Test]
    public function it_makes_a_uuid_for_a_date(): void
    {
        $date = new DateTimeImmutable('2025-01-02 03:04:05.678');

        $actual = UuidV7::make($date);

        $this->assertSame(
            $date->format('Y-m-d H:i:s.v'),
            $actual->value->getDateTime()->format('Y-m-d H:i:s.v'),
        );
    }

    #[Test]
    public function it_can_be_compared_for_sorting(): void
    {
        $uuid1 = UuidV7::make();
        $uuid2 = UuidV7::make();
        $uuid3 = UuidV7::make();
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
    public function it_returns_the_same_instance_when_created_from_a_uuid_v7(): void
    {
        $id = UuidV7::make();

        $actual = UuidV7::from($id);

        $this->assertSame($id, $actual);
    }

    #[Test]
    public function it_returns_the_same_instance_when_trying_from_a_uuid_v7(): void
    {
        $id = UuidV7::make();

        $actual = UuidV7::tryFrom($id);

        $this->assertSame($id, $actual);
    }

    #[Test]
    public function it_tries_to_create_from_a_ramsey_uuid(): void
    {
        $base = RamseyUuid::uuid7();

        $actual = UuidV7::tryFrom($base);

        $this->assertEquals(UuidV7::from($base), $actual);
    }

    #[Test]
    public function it_can_be_created_from_a_string(): void
    {
        $expected = UuidV7::from(RamseyUuid::fromString(self::UUID));

        $actual = UuidV7::from(self::UUID);

        $this->assertObjectEquals($expected, $actual);
    }

    #[Test]
    public function it_can_try_to_create_from_a_string(): void
    {
        $expected = UuidV7::from(RamseyUuid::fromString(self::UUID));

        $actual = UuidV7::tryFrom(self::UUID);

        $this->assertNotNull($actual);
        $this->assertObjectEquals($expected, $actual);
    }

    #[Test]
    public function it_returns_null_when_trying_from_an_invalid_string(): void
    {
        $actual = UuidV7::tryFrom('invalid');

        $this->assertNull($actual);
    }

    /**
     * @return array<string, array{0: Identifier|null}>
     */
    public static function notUuidV7Provider(): array
    {
        return [
            'null' => [null],
            'integer id' => [new IntegerId(1)],
            'string id' => [new StringId('foo')],
            'guid' => [new Guid('SomeType', new Uuid(RamseyUuid::fromString('6dcbad65-ed92-4e60-973b-9ba58a022816')))],
            'uuid' => [Uuid::random()],
            'uuid v4' => [UuidV4::make()],
        ];
    }

    #[DataProvider('notUuidV7Provider')]
    #[Test]
    public function it_cannot_be_created_from_other_identifier_types(?Identifier $other): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Unexpected identifier type, received: ' . get_debug_type($other));

        UuidV7::from($other);
    }

    #[DataProvider('notUuidV7Provider')]
    #[Test]
    public function it_returns_null_when_trying_from_other_identifier_types(?Identifier $other): void
    {
        $actual = UuidV7::tryFrom($other);

        $this->assertNull($actual);
    }

    // -------------------------------------------------------------------------
    // is(), any() and equals()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_equals_a_uuid_with_the_same_value(): void
    {
        $id = UuidV7::from(self::UUID);

        $this->assertObjectEquals(UuidV7::from(self::UUID), $id);
    }

    #[Test]
    public function it_does_not_equal_a_uuid_with_a_different_value(): void
    {
        $id = UuidV7::from(self::UUID);

        $this->assertObjectNotEquals(UuidV7::from(self::OTHER_UUID), $id);
    }

    #[Test]
    public function it_is_a_uuid_v7_with_the_same_value(): void
    {
        $base = RamseyUuid::uuid7();
        $id = UuidV7::from($base);

        $actual = $id->is(UuidV7::from($base));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_a_uuid_with_the_same_value(): void
    {
        $base = RamseyUuid::uuid7();
        $id = UuidV7::from($base);

        $actual = $id->is(new Uuid($base));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_not_a_uuid_with_a_different_value(): void
    {
        $id = UuidV7::from(self::UUID);

        $actual = $id->is(UuidV7::from(self::OTHER_UUID));

        $this->assertFalse($actual);
    }

    #[DataProvider('notUuidV7Provider')]
    #[Test]
    public function it_is_not_equal_to_other_identifier_types(?Identifier $other): void
    {
        $id = UuidV7::from(self::UUID);

        $actual = $id->is($other);

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_any_of_the_provided_identifiers_when_one_matches(): void
    {
        $base = RamseyUuid::uuid7();
        $id = UuidV7::from($base);

        $actual = $id->any(null, UuidV7::make(), UuidV7::from($base));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_not_any_of_the_provided_identifiers_when_none_match(): void
    {
        $id = UuidV7::from(self::UUID);

        $actual = $id->any(null, UuidV7::make(), UuidV7::from(self::OTHER_UUID));

        $this->assertFalse($actual);
    }

    #[DataProvider('notUuidV7Provider')]
    #[Test]
    public function it_is_not_any_of_other_identifier_types(?Identifier $other): void
    {
        $id = UuidV7::from(self::UUID);

        $actual = $id->any(null, UuidV7::make(), $other);

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_not_any_when_no_identifiers_are_provided(): void
    {
        $id = UuidV7::from(self::UUID);

        $actual = $id->any();

        $this->assertFalse($actual);
    }
}
