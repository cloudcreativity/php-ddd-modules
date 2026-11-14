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

    // -------------------------------------------------------------------------
    // from() and tryFrom()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_is_created_from_a_string(): void
    {
        $actual = StringId::from('99');

        $this->assertObjectEquals(new StringId('99'), $actual);
    }

    #[Test]
    public function it_returns_the_same_instance_when_created_from_a_string_id(): void
    {
        $id = new StringId('99');

        $actual = StringId::from($id);

        $this->assertSame($id, $actual);
    }

    #[Test]
    public function it_tries_to_create_from_a_string(): void
    {
        $actual = StringId::tryFrom('99');

        $this->assertNotNull($actual);
        $this->assertObjectEquals(new StringId('99'), $actual);
    }

    #[Test]
    public function it_returns_the_same_instance_when_trying_from_a_string_id(): void
    {
        $id = new StringId('99');

        $actual = StringId::tryFrom($id);

        $this->assertSame($id, $actual);
    }

    /**
     * @return array<string, array{0: Identifier|null}>
     */
    public static function notStringIdProvider(): array
    {
        return [
            'null' => [null],
            'integer id' => [new IntegerId(1)],
            'guid' => [new Guid('SomeType', new StringId('1'))],
            'uuid' => [new Uuid(RamseyUuid::uuid4())],
        ];
    }

    #[DataProvider('notStringIdProvider')]
    #[Test]
    public function it_cannot_be_created_from_other_identifier_types(?Identifier $other): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Unexpected identifier type, received: ' . get_debug_type($other));

        StringId::from($other);
    }

    #[DataProvider('notStringIdProvider')]
    #[Test]
    public function it_returns_null_when_trying_from_other_identifier_types(?Identifier $other): void
    {
        $actual = StringId::tryFrom($other);

        $this->assertNull($actual);
    }

    // -------------------------------------------------------------------------
    // is(), any() and equals()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_equals_a_string_id_with_the_same_value(): void
    {
        $id = new StringId('99');

        $this->assertObjectEquals(new StringId('99'), $id);
    }

    #[Test]
    public function it_does_not_equal_a_string_id_with_a_different_value(): void
    {
        $id = new StringId('99');

        $this->assertObjectNotEquals(new StringId('100'), $id);
    }

    #[Test]
    public function it_is_a_string_id_with_the_same_value(): void
    {
        $id = new StringId('99');

        $actual = $id->is(new StringId('99'));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_not_a_string_id_with_a_different_value(): void
    {
        $id = new StringId('99');

        $actual = $id->is(new StringId('100'));

        $this->assertFalse($actual);
    }

    #[DataProvider('notStringIdProvider')]
    #[Test]
    public function it_is_not_equal_to_other_identifier_types(?Identifier $other): void
    {
        $id = new StringId('1');

        $actual = $id->is($other);

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_any_of_the_provided_identifiers_when_one_matches(): void
    {
        $id = new StringId('99');

        $actual = $id->any(new StringId('foo'), new StringId('bar'), null, new StringId('99'));

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_is_not_any_of_the_provided_identifiers_when_none_match(): void
    {
        $id = new StringId('99');

        $actual = $id->any(new StringId('foo'), new StringId('bar'), null, new StringId('100'));

        $this->assertFalse($actual);
    }

    #[DataProvider('notStringIdProvider')]
    #[Test]
    public function it_is_not_any_of_other_identifier_types(?Identifier $other): void
    {
        $id = new StringId('1');

        $actual = $id->any($other, new StringId('foo'), null);

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_is_not_any_when_no_identifiers_are_provided(): void
    {
        $id = new StringId('99');

        $actual = $id->any();

        $this->assertFalse($actual);
    }
}
