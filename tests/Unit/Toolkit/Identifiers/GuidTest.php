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
use CloudCreativity\Modules\Contracts\Toolkit\Identifiers\Uuid as IUuid;
use CloudCreativity\Modules\Tests\TestBackedEnum;
use CloudCreativity\Modules\Tests\TestUnitEnum;
use CloudCreativity\Modules\Toolkit\ContractException;
use CloudCreativity\Modules\Toolkit\Identifiers\Guid;
use CloudCreativity\Modules\Toolkit\Identifiers\IntegerId;
use CloudCreativity\Modules\Toolkit\Identifiers\StringId;
use CloudCreativity\Modules\Toolkit\Identifiers\Uuid;
use CloudCreativity\Modules\Toolkit\Identifiers\UuidV4;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as BaseUuid;
use Ramsey\Uuid\UuidInterface;
use Stringable;
use UnitEnum;

final class GuidTest extends TestCase
{
    private const string UUID = '6dcbad65-ed92-4e60-973b-9ba58a022816';

    private const string OTHER_UUID = '38c7be26-6887-4742-8b6b-7d07b30ca596';

    /**
     * @return array<string, array{0: string|UnitEnum, 1: string, 2: string|UnitEnum, 3: string}>
     */
    public static function typeProvider(): array
    {
        return [
            'string' => ['SomeType', 'SomeType', 'Other', 'Other'],
            'unit enum' => [
                TestUnitEnum::Baz,
                TestUnitEnum::Baz->name,
                TestUnitEnum::Bat,
                TestUnitEnum::Bat->name,
            ],
            'backed enum' => [
                TestBackedEnum::Foo,
                TestBackedEnum::Foo->value,
                TestBackedEnum::Bar,
                TestBackedEnum::Bar->value,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Creation
    // -------------------------------------------------------------------------

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_can_be_created_from_a_string_id(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $guid = Guid::fromString($type, '123');

        $this->assertInstanceOf(Stringable::class, $guid);
        $this->assertSame($type, $guid->type);
        $this->assertObjectEquals(new StringId('123'), $guid->id);
        $this->assertSame($value . ':123', $guid->toString());
        $this->assertSame($value . ':123', (string) $guid);
        $this->assertSame(['type' => $value, 'id' => '123'], $guid->context());
    }

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_can_be_created_from_an_integer_id(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $guid = Guid::fromInteger($type, 123);

        $this->assertSame($type, $guid->type);
        $this->assertObjectEquals(new IntegerId(123), $guid->id);
        $this->assertSame($value . ':123', $guid->toString());
        $this->assertSame($value . ':123', (string) $guid);
        $this->assertSame(['type' => $value, 'id' => 123], $guid->context());
    }

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_can_be_created_from_a_base_uuid(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $uuid = new Uuid(BaseUuid::fromString(self::UUID));

        $guid = Guid::fromUuid($type, $uuid->value);

        $this->assertSame($type, $guid->type);
        $this->assertTrue($uuid->is($guid->id));
        $this->assertSame($value . ':' . self::UUID, $guid->toString());
        $this->assertSame($value . ':' . self::UUID, (string) $guid);
        $this->assertSame(['type' => $value, 'id' => self::UUID], $guid->context());
    }

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_can_be_created_from_a_uuid_identifier(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $uuid = new Uuid(BaseUuid::fromString(self::UUID));

        $actual = Guid::fromUuid($type, $uuid);

        $this->assertObjectEquals(Guid::fromUuid($type, $uuid->value), $actual);
    }

    #[Test]
    public function it_rejects_an_empty_type(): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Type must be a non-empty string.');

        Guid::fromString('', '123');
    }

    #[Test]
    public function it_rejects_an_empty_type_when_constructed(): void
    {
        $id = new IntegerId(1);

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Type must be a non-empty string.');

        new Guid('', $id);
    }

    #[Test]
    public function it_rejects_an_empty_string_id(): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Identifier value must be a non-empty string.');

        Guid::fromString('SomeType', '');
    }

    #[Test]
    public function it_rejects_a_negative_integer_id(): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Identifier value must be greater than zero.');

        Guid::fromInteger('SomeType', -1);
    }

    // -------------------------------------------------------------------------
    // make()
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{0: int|string|UuidInterface|UuidV4, 1: Identifier}>
     */
    public static function makeProvider(): array
    {
        $uuid = UuidV4::make();

        return [
            'integer' => [123, new IntegerId(123)],
            'string' => ['some-slug', new StringId('some-slug')],
            'ramsey uuid' => [$uuid->value, $uuid],
            'uuid object' => [$uuid, $uuid],
            'string uuid' => [$uuid->toString(), $uuid],
        ];
    }

    #[DataProvider('makeProvider')]
    #[Test]
    public function it_makes_guid(int|IUuid|string|UuidInterface $value, Identifier $expected): void
    {
        $guid = Guid::make('SomeType', $value);

        $this->assertSame('SomeType', $guid->type);
        $this->assertTrue($expected->is($guid->id));
    }

    // -------------------------------------------------------------------------
    // from() and tryFrom()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_the_same_instance_when_created_from_a_guid(): void
    {
        $guid = Guid::fromInteger('SomeType', 1);

        $actual = Guid::from($guid);

        $this->assertSame($guid, $actual);
    }

    #[Test]
    public function it_returns_the_same_instance_when_trying_from_a_guid(): void
    {
        $guid = Guid::fromInteger('SomeType', 1);

        $actual = Guid::tryFrom($guid);

        $this->assertSame($guid, $actual);
    }

    /**
     * @return array<string, array{0: Identifier|null}>
     */
    public static function notGuidProvider(): array
    {
        return [
            'null' => [null],
            'integer id' => [new IntegerId(1)],
            'string id' => [new StringId('SomeType:1')],
            'uuid' => [new Uuid(BaseUuid::fromString(self::UUID))],
        ];
    }

    #[DataProvider('notGuidProvider')]
    #[Test]
    public function it_cannot_be_created_from_other_identifier_types(?Identifier $other): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Unexpected identifier type, received: ' . get_debug_type($other));

        Guid::from($other);
    }

    #[DataProvider('notGuidProvider')]
    #[Test]
    public function it_returns_null_when_trying_from_other_identifier_types(?Identifier $other): void
    {
        $actual = Guid::tryFrom($other);

        $this->assertNull($actual);
    }

    // -------------------------------------------------------------------------
    // is(), any() and equals()
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{0: Guid}>
     */
    public static function guidProvider(): array
    {
        return [
            'string id' => [Guid::fromString('SomeType', '123')],
            'string id with enum type' => [Guid::fromString(TestUnitEnum::Baz, '123')],
            'integer id' => [Guid::fromInteger('SomeType', 123)],
            'integer id with enum type' => [Guid::fromInteger(TestBackedEnum::Foo, 123)],
            'uuid' => [Guid::fromUuid('SomeType', self::UUID)],
            'uuid with enum type' => [Guid::fromUuid(TestUnitEnum::Bat, self::UUID)],
        ];
    }

    #[DataProvider('guidProvider')]
    #[Test]
    public function it_is_the_same_instance(Guid $guid): void
    {
        $actual = $guid->is($guid);

        $this->assertTrue($actual);
    }

    #[DataProvider('guidProvider')]
    #[Test]
    public function it_is_a_guid_with_the_same_values(Guid $guid): void
    {
        $other = clone $guid;

        $actual = $guid->is($other);

        $this->assertTrue($actual);
    }

    #[DataProvider('guidProvider')]
    #[Test]
    public function it_equals_a_guid_with_the_same_values(Guid $guid): void
    {
        $other = new Guid($guid->type, $guid->id);

        $this->assertObjectEquals($other, $guid);
    }

    /**
     * @return array<string, array{0: Guid, 1: Guid}>
     */
    public static function differentGuidProvider(): array
    {
        return [
            'string id vs integer id' => [Guid::fromString('SomeType', '123'), Guid::fromInteger('SomeType', 123)],
            'different string id' => [Guid::fromString('SomeType', '123'), Guid::fromString('SomeType', '234')],
            'string id with different type' => [Guid::fromString('SomeType', '123'), Guid::fromString('Other', '123')],
            'string id with different enum type' => [
                Guid::fromString(TestUnitEnum::Baz, '123'),
                Guid::fromString(TestUnitEnum::Bat, '123'),
            ],
            'integer id vs string id' => [Guid::fromInteger('SomeType', 123), Guid::fromString('SomeType', '123')],
            'different integer id' => [Guid::fromInteger('SomeType', 123), Guid::fromInteger('SomeType', 234)],
            'integer id with different type' => [Guid::fromInteger('SomeType', 123), Guid::fromInteger('Other', 123)],
            'integer id with different enum type' => [
                Guid::fromInteger(TestBackedEnum::Foo, 123),
                Guid::fromInteger(TestBackedEnum::Bar, 123),
            ],
            'uuid with different type' => [Guid::fromUuid('SomeType', self::UUID), Guid::fromUuid('Other', self::UUID)],
            'different uuid' => [Guid::fromUuid('SomeType', self::UUID), Guid::fromUuid('SomeType', self::OTHER_UUID)],
            'uuid vs string id' => [Guid::fromUuid('SomeType', self::UUID), Guid::fromString('SomeType', self::UUID)],
            'uuid vs integer id' => [Guid::fromUuid('SomeType', self::UUID), Guid::fromInteger('SomeType', 234)],
        ];
    }

    #[DataProvider('differentGuidProvider')]
    #[Test]
    public function it_is_not_a_guid_with_different_values(Guid $guid, Guid $other): void
    {
        $actual = $guid->is($other);

        $this->assertFalse($actual);
    }

    #[DataProvider('differentGuidProvider')]
    #[Test]
    public function it_does_not_equal_a_guid_with_different_values(Guid $guid, Guid $other): void
    {
        $this->assertObjectNotEquals($other, $guid);
    }

    #[DataProvider('notGuidProvider')]
    #[Test]
    public function it_is_not_equal_to_other_identifier_types(?Identifier $other): void
    {
        $guid = Guid::fromInteger('SomeType', 1);

        $actual = $guid->is($other);

        $this->assertFalse($actual);
    }

    #[DataProvider('notGuidProvider')]
    #[Test]
    public function it_is_not_any_of_other_identifier_types(?Identifier $other): void
    {
        $guid = Guid::fromInteger('SomeType', 1);

        $actual = $guid->any($other);

        $this->assertFalse($actual);
    }

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_is_any_of_the_provided_guids_when_one_matches(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $guid = Guid::fromString($type, '123');

        $actual = $guid->any(Guid::fromInteger($type, 123), Guid::fromString($type, '234'), clone $guid);

        $this->assertTrue($actual);
    }

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_is_not_any_of_the_provided_guids_when_none_match(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $guid = Guid::fromString($type, '123');

        $actual = $guid->any(
            Guid::fromInteger($type, 123),
            Guid::fromString($type, '234'),
            Guid::fromString($other, '123'),
        );

        $this->assertFalse($actual);
    }

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_is_not_any_of_the_provided_guids_including_null_when_none_match(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $guid = Guid::fromString($type, '123');

        $actual = $guid->any(
            null,
            Guid::fromInteger($type, 123),
            Guid::fromString($type, '234'),
            Guid::fromString($other, '123'),
        );

        $this->assertFalse($actual);
    }

    // -------------------------------------------------------------------------
    // isType() and assertType()
    // -------------------------------------------------------------------------

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_is_the_type(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $guid = Guid::fromInteger($type, 1);

        $actual = $guid->isType($type);

        $this->assertTrue($actual);
    }

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_is_any_of_multiple_types(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $guid = Guid::fromInteger($type, 1);

        $actual = $guid->isType($other, $type);

        $this->assertTrue($actual);
    }

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_is_not_any_of_other_types(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $guid = Guid::fromInteger($type, 1);

        $actual = $guid->isType($other, 'Blah!');

        $this->assertFalse($actual);
    }

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_is_not_a_type_when_no_types_are_provided(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $guid = Guid::fromInteger($type, 1);

        $actual = $guid->isType();

        $this->assertFalse($actual);
    }

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_does_not_throw_when_asserting_the_expected_type(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $guid = Guid::fromInteger($type, 1);

        $actual = $guid->assertType($type);

        $this->assertSame($guid, $actual);
    }

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_throws_when_asserting_an_unexpected_type(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $guid = Guid::fromInteger($type, 1);

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage(sprintf(
            'Expecting type "%s", received "%s".',
            $otherValue,
            $value,
        ));

        $guid->assertType($other);
    }

    #[Test]
    public function it_throws_with_a_custom_message_when_asserting_an_unexpected_type(): void
    {
        $guid = Guid::fromInteger('SomeType', 1);

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Not the expected type!');

        $guid->assertType('OtherType', 'Not the expected type!');
    }

    // -------------------------------------------------------------------------
    // key() and toString()
    // -------------------------------------------------------------------------

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_returns_a_key(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $guid = Guid::fromInteger($type, 123);

        $actual = $guid->key();

        $this->assertSame($value . ':123', $actual);
    }

    #[DataProvider('typeProvider')]
    #[Test]
    public function it_converts_to_a_string_with_a_custom_glue(
        string|UnitEnum $type,
        string $value,
        string|UnitEnum $other,
        string $otherValue,
    ): void {
        $guid = Guid::fromString($type, 'abc');

        $actual = $guid->toString('/');

        $this->assertSame($value . '/abc', $actual);
    }
}
