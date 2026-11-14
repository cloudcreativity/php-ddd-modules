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
use CloudCreativity\Modules\Contracts\Toolkit\Identifiers\UuidFactory;
use CloudCreativity\Modules\Toolkit\ContractException;
use CloudCreativity\Modules\Toolkit\Identifiers\Guid;
use CloudCreativity\Modules\Toolkit\Identifiers\IntegerId;
use CloudCreativity\Modules\Toolkit\Identifiers\StringId;
use CloudCreativity\Modules\Toolkit\Identifiers\Uuid;
use CloudCreativity\Modules\Toolkit\Identifiers\UuidFactory as ConcreteUuidFactory;
use CloudCreativity\Modules\Toolkit\Identifiers\UuidV4;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class UuidTest extends TestCase
{
    private const string UUID = '6dcbad65-ed92-4e60-973b-9ba58a022816';

    private const string OTHER_UUID = '38c7be26-6887-4742-8b6b-7d07b30ca596';

    protected function tearDown(): void
    {
        Uuid::setFactory(null);
        parent::tearDown();
    }

    #[Test]
    public function it_wraps_a_ramsey_uuid(): void
    {
        $base = RamseyUuid::uuid4();

        $id = new Uuid($base);

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
    public function it_creates_a_nil_uuid(): void
    {
        $base = RamseyUuid::fromString(RamseyUuid::NIL);

        $actual = Uuid::nil();

        $this->assertTrue($actual->toBase()->equals($base));
    }

    #[Test]
    public function it_can_be_compared_for_sorting(): void
    {
        $uuid1 = Uuid::random();
        $uuid2 = Uuid::random();
        $uuid3 = Uuid::random();
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
    public function it_returns_the_same_instance_when_created_from_a_uuid(): void
    {
        $id = new Uuid(RamseyUuid::uuid4());

        $actual = Uuid::from($id);

        $this->assertSame($id, $actual);
    }

    #[Test]
    public function it_returns_the_same_instance_when_trying_from_a_uuid(): void
    {
        $id = new Uuid(RamseyUuid::uuid4());

        $actual = Uuid::tryFrom($id);

        $this->assertSame($id, $actual);
    }

    #[Test]
    public function it_returns_the_same_instance_when_created_from_another_uuid_type(): void
    {
        $other = UuidV4::make();

        $actual = Uuid::from($other);

        $this->assertSame($other, $actual);
    }

    #[Test]
    public function it_returns_the_same_instance_when_trying_from_another_uuid_type(): void
    {
        $other = UuidV4::make();

        $actual = Uuid::tryFrom($other);

        $this->assertSame($other, $actual);
    }

    #[Test]
    public function it_can_be_created_from_a_string(): void
    {
        $factory = $this->createMock(UuidFactory::class);
        Uuid::setFactory($factory);
        $expected = new Uuid(RamseyUuid::uuid4());

        $factory
            ->expects($this->once())
            ->method('fromString')
            ->with('blah!')
            ->willReturn($expected);

        $actual = Uuid::from('blah!');

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_can_try_to_create_from_a_string(): void
    {
        $factory = $this->createMock(UuidFactory::class);
        Uuid::setFactory($factory);
        $expected = new Uuid(RamseyUuid::fromString(self::UUID));

        $factory
            ->expects($this->once())
            ->method('fromString')
            ->with(self::UUID)
            ->willReturn($expected);

        $actual = Uuid::tryFrom(self::UUID);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_returns_null_when_trying_from_an_invalid_string(): void
    {
        $factory = $this->createMock(UuidFactory::class);
        Uuid::setFactory($factory);

        $factory
            ->expects($this->never())
            ->method($this->anything());

        $actual = Uuid::tryFrom('invalid');

        $this->assertNull($actual);
    }

    #[Test]
    public function it_can_be_created_from_a_base_uuid(): void
    {
        $factory = $this->createMock(UuidFactory::class);
        Uuid::setFactory($factory);
        $base = RamseyUuid::uuid4();
        $expected = new Uuid($base);

        $factory
            ->expects($this->once())
            ->method('from')
            ->with($this->identicalTo($base))
            ->willReturn($expected);

        $actual = Uuid::from($base);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_can_try_to_create_from_a_base_uuid(): void
    {
        $factory = $this->createMock(UuidFactory::class);
        Uuid::setFactory($factory);
        $base = RamseyUuid::uuid4();
        $expected = new Uuid($base);

        $factory
            ->expects($this->once())
            ->method('from')
            ->with($this->identicalTo($base))
            ->willReturn($expected);

        $actual = Uuid::tryFrom($base);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_returns_null_when_trying_from_null(): void
    {
        $factory = $this->createMock(UuidFactory::class);
        Uuid::setFactory($factory);

        $factory
            ->expects($this->never())
            ->method($this->anything());

        $actual = Uuid::tryFrom(null);

        $this->assertNull($actual);
    }

    /**
     * @return array<string, array{0: Identifier|null}>
     */
    public static function notUuidProvider(): array
    {
        return [
            'null' => [null],
            'integer id' => [new IntegerId(1)],
            'string id' => [new StringId('foo')],
            'guid' => [new Guid('SomeType', new Uuid(RamseyUuid::fromString(self::UUID)))],
        ];
    }

    #[DataProvider('notUuidProvider')]
    #[Test]
    public function it_cannot_be_created_from_other_identifier_types(?Identifier $other): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Unexpected identifier type, received: ' . get_debug_type($other));

        Uuid::from($other);
    }

    #[DataProvider('notUuidProvider')]
    #[Test]
    public function it_returns_null_when_trying_from_other_identifier_types(?Identifier $other): void
    {
        $actual = Uuid::tryFrom($other);

        $this->assertNull($actual);
    }

    // -------------------------------------------------------------------------
    // is(), any() and equals()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_equals_a_uuid_with_the_same_value(): void
    {
        $id = new Uuid(RamseyUuid::fromString(self::UUID));

        $this->assertObjectEquals(new Uuid(RamseyUuid::fromString(self::UUID)), $id);
    }

    #[Test]
    public function it_does_not_equal_a_uuid_with_a_different_value(): void
    {
        $id = new Uuid(RamseyUuid::fromString(self::UUID));

        $this->assertObjectNotEquals(new Uuid(RamseyUuid::fromString(self::OTHER_UUID)), $id);
    }

    #[Test]
    public function it_is_a_uuid_with_the_same_value(): void
    {
        $base = RamseyUuid::uuid4();
        $id = new Uuid($base);

        $actual = $id->is(new Uuid($base));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_not_a_uuid_with_a_different_value(): void
    {
        $id = new Uuid(RamseyUuid::fromString(self::UUID));

        $actual = $id->is(new Uuid(RamseyUuid::fromString(self::OTHER_UUID)));

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_the_same_as_a_uuid_v4_with_the_same_value(): void
    {
        $other = UuidV4::make();
        $id = new Uuid($other->value);

        $actual = $id->is($other);

        $this->assertTrue($actual);
    }

    #[DataProvider('notUuidProvider')]
    #[Test]
    public function it_is_not_equal_to_other_identifier_types(?Identifier $other): void
    {
        $id = new Uuid(RamseyUuid::fromString(self::UUID));

        $actual = $id->is($other);

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_any_of_the_provided_identifiers_when_one_matches(): void
    {
        $base = RamseyUuid::uuid4();
        $id = new Uuid($base);

        $actual = $id->any(null, Uuid::random(), new Uuid($base));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_not_any_of_the_provided_identifiers_when_none_match(): void
    {
        $id = new Uuid(RamseyUuid::fromString(self::UUID));

        $actual = $id->any(null, Uuid::random(), new Uuid(RamseyUuid::fromString(self::OTHER_UUID)));

        $this->assertFalse($actual);
    }

    #[DataProvider('notUuidProvider')]
    #[Test]
    public function it_is_not_any_of_other_identifier_types(?Identifier $other): void
    {
        $id = new Uuid(RamseyUuid::fromString(self::UUID));

        $actual = $id->any(null, Uuid::random(), $other);

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_not_any_when_no_identifiers_are_provided(): void
    {
        $id = new Uuid(RamseyUuid::fromString(self::UUID));

        $actual = $id->any();

        $this->assertFalse($actual);
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    #[Test]
    public function it_uses_a_default_factory(): void
    {
        Uuid::setFactory(null);

        $actual = Uuid::getFactory();

        $this->assertInstanceOf(ConcreteUuidFactory::class, $actual);
    }

    #[Test]
    public function it_reuses_the_default_factory(): void
    {
        Uuid::setFactory(null);
        $expected = Uuid::getFactory();

        $actual = Uuid::getFactory();

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_uses_the_provided_factory(): void
    {
        $factory = $this->createStub(UuidFactory::class);
        Uuid::setFactory($factory);

        $actual = Uuid::getFactory();

        $this->assertSame($factory, $actual);
    }

    #[Test]
    public function it_generates_a_random_uuid_using_the_factory(): void
    {
        $expected = UuidV4::make();
        $factory = $this->createMock(UuidFactory::class);
        Uuid::setFactory($factory);

        $factory
            ->expects($this->once())
            ->method('uuid4')
            ->willReturn($expected);

        $actual = Uuid::random();

        $this->assertSame($expected, $actual);
    }
}
