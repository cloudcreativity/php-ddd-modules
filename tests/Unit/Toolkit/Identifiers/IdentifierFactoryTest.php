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
use CloudCreativity\Modules\Contracts\Toolkit\Identifiers\IdentifierFactory as IIdentifierFactory;
use CloudCreativity\Modules\Toolkit\ContractException;
use CloudCreativity\Modules\Toolkit\Identifiers\Guid;
use CloudCreativity\Modules\Toolkit\Identifiers\IdentifierFactory;
use CloudCreativity\Modules\Toolkit\Identifiers\IntegerId;
use CloudCreativity\Modules\Toolkit\Identifiers\StringId;
use CloudCreativity\Modules\Toolkit\Identifiers\Uuid;
use CloudCreativity\Modules\Toolkit\Identifiers\UuidV7;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;
use stdClass;

final class IdentifierFactoryTest extends TestCase
{
    #[Test]
    public function it_is_an_identifier_factory(): void
    {
        $factory = new IdentifierFactory();

        $this->assertInstanceOf(IIdentifierFactory::class, $factory);
    }

    /**
     * @return array<string, array{0: Identifier}>
     */
    public static function identifierProvider(): array
    {
        return [
            'integer id' => [new IntegerId(1)],
            'string id' => [new StringId('foo')],
            'uuid' => [Uuid::random()],
            'uuid v7' => [UuidV7::make()],
            'guid' => [Guid::fromInteger('SomeType', 1)],
        ];
    }

    #[DataProvider('identifierProvider')]
    #[Test]
    public function it_returns_an_identifier_as_is(Identifier $id): void
    {
        $factory = new IdentifierFactory();

        $actual = $factory->make($id);

        $this->assertSame($id, $actual);
    }

    #[Test]
    public function it_wraps_a_base_uuid(): void
    {
        $base = RamseyUuid::uuid4();
        $factory = new IdentifierFactory();

        $actual = $factory->make($base);

        $this->assertObjectEquals(new Uuid($base), $actual);
    }

    #[Test]
    public function it_wraps_an_integer(): void
    {
        $factory = new IdentifierFactory();

        $actual = $factory->make(123);

        $this->assertObjectEquals(new IntegerId(123), $actual);
    }

    #[Test]
    public function it_wraps_a_string(): void
    {
        $factory = new IdentifierFactory();

        $actual = $factory->make('foo');

        $this->assertObjectEquals(new StringId('foo'), $actual);
    }

    #[Test]
    public function it_wraps_a_uuid_string_in_a_string_id(): void
    {
        $value = RamseyUuid::uuid4()->toString();
        $factory = new IdentifierFactory();

        $actual = $factory->make($value);

        $this->assertObjectEquals(new StringId($value), $actual);
    }

    #[Test]
    public function it_rejects_an_invalid_integer(): void
    {
        $factory = new IdentifierFactory();

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Identifier value must be greater than zero.');

        $factory->make(0);
    }

    #[Test]
    public function it_rejects_an_empty_string(): void
    {
        $factory = new IdentifierFactory();

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Identifier value must be a non-empty string.');

        $factory->make('');
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function invalidProvider(): array
    {
        return [
            'null' => [null, 'null'],
            'float' => [1.5, 'float'],
            'bool' => [true, 'bool'],
            'array' => [[1], 'array'],
            'object' => [new stdClass(), 'stdClass'],
        ];
    }

    #[DataProvider('invalidProvider')]
    #[Test]
    public function it_rejects_unexpected_values(mixed $value, string $type): void
    {
        $factory = new IdentifierFactory();

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Unexpected identifier type, received: ' . $type);

        $factory->make($value);
    }
}
