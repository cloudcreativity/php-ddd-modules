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
use Ramsey\Uuid\Uuid as RamseyUuid;

final class IntegerIdTest extends TestCase
{
    #[Test]
    public function it_wraps_an_integer(): void
    {
        $id = new IntegerId(99);

        $this->assertSame(99, $id->value);
        $this->assertSame(99, $id->context());
        $this->assertSame(99, $id->key());
        $this->assertSame('99', $id->toString());
        $this->assertSame('99', (string) $id);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['id' => 99], \JSON_THROW_ON_ERROR),
            json_encode(compact('id'), \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function it_must_be_greater_than_zero(): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Identifier value must be greater than zero.');

        new IntegerId(0);
    }

    // -------------------------------------------------------------------------
    // from() and tryFrom()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_is_created_from_an_integer(): void
    {
        $actual = IntegerId::from(99);

        $this->assertObjectEquals(new IntegerId(99), $actual);
    }

    #[Test]
    public function it_returns_the_same_instance_when_created_from_an_integer_id(): void
    {
        $id = new IntegerId(99);

        $actual = IntegerId::from($id);

        $this->assertSame($id, $actual);
    }

    #[Test]
    public function it_tries_to_create_from_an_integer(): void
    {
        $actual = IntegerId::tryFrom(99);

        $this->assertNotNull($actual);
        $this->assertObjectEquals(new IntegerId(99), $actual);
    }

    #[Test]
    public function it_returns_the_same_instance_when_trying_from_an_integer_id(): void
    {
        $id = new IntegerId(99);

        $actual = IntegerId::tryFrom($id);

        $this->assertSame($id, $actual);
    }

    /**
     * @return array<string, array{0: Identifier|null}>
     */
    public static function notIntegerIdProvider(): array
    {
        return [
            'null' => [null],
            'string id' => [new StringId('1')],
            'guid' => [new Guid('SomeType', new IntegerId(1))],
            'uuid' => [new Uuid(RamseyUuid::uuid4())],
        ];
    }

    #[DataProvider('notIntegerIdProvider')]
    #[Test]
    public function it_cannot_be_created_from_other_identifier_types(?Identifier $other): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Unexpected identifier type, received: ' . get_debug_type($other));

        IntegerId::from($other);
    }

    #[DataProvider('notIntegerIdProvider')]
    #[Test]
    public function it_returns_null_when_trying_from_other_identifier_types(?Identifier $other): void
    {
        $actual = IntegerId::tryFrom($other);

        $this->assertNull($actual);
    }

    // -------------------------------------------------------------------------
    // is(), any() and equals()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_equals_an_integer_id_with_the_same_value(): void
    {
        $id = new IntegerId(99);

        $this->assertObjectEquals(new IntegerId(99), $id);
    }

    #[Test]
    public function it_does_not_equal_an_integer_id_with_a_different_value(): void
    {
        $id = new IntegerId(99);

        $this->assertObjectNotEquals(new IntegerId(100), $id);
    }

    #[Test]
    public function it_is_an_integer_id_with_the_same_value(): void
    {
        $id = new IntegerId(99);

        $actual = $id->is(new IntegerId(99));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_not_an_integer_id_with_a_different_value(): void
    {
        $id = new IntegerId(99);

        $actual = $id->is(new IntegerId(100));

        $this->assertFalse($actual);
    }

    #[DataProvider('notIntegerIdProvider')]
    #[Test]
    public function it_is_not_equal_to_other_identifier_types(?Identifier $other): void
    {
        $id = new IntegerId(1);

        $actual = $id->is($other);

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_any_of_the_provided_identifiers_when_one_matches(): void
    {
        $id = new IntegerId(99);

        $actual = $id->any(new IntegerId(1), new IntegerId(2), new IntegerId(99));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_not_any_of_the_provided_identifiers_when_none_match(): void
    {
        $id = new IntegerId(99);

        $actual = $id->any(new IntegerId(100), new IntegerId(200), new IntegerId(300));

        $this->assertFalse($actual);
    }

    #[DataProvider('notIntegerIdProvider')]
    #[Test]
    public function it_is_not_any_of_other_identifier_types(?Identifier $other): void
    {
        $id = new IntegerId(1);

        $actual = $id->any(new IntegerId(2), null, $other);

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_not_any_when_no_identifiers_are_provided(): void
    {
        $id = new IntegerId(99);

        $actual = $id->any();

        $this->assertFalse($actual);
    }
}
