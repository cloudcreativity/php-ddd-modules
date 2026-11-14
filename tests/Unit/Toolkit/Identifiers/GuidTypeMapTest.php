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

use CloudCreativity\Modules\Tests\TestBackedEnum;
use CloudCreativity\Modules\Tests\TestBackedIntEnum;
use CloudCreativity\Modules\Tests\TestUnitEnum;
use CloudCreativity\Modules\Toolkit\Identifiers\Guid;
use CloudCreativity\Modules\Toolkit\Identifiers\GuidTypeMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnitEnum;

final class GuidTypeMapTest extends TestCase
{
    private const string UUID = '6dcbad65-ed92-4e60-973b-9ba58a022816';

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function stringTypeProvider(): array
    {
        return [
            'a' => ['SomeTypeA', 'SomeOtherTypeA'],
            'b' => ['SomeTypeB', 'SomeOtherTypeB'],
            'c' => ['SomeTypeC', 'SomeOtherTypeC'],
        ];
    }

    #[DataProvider('stringTypeProvider')]
    #[Test]
    public function it_returns_expected_type(string $alias, string $expected): void
    {
        $map = new GuidTypeMap([
            'SomeTypeA' => 'SomeOtherTypeA',
            'SomeTypeB' => 'SomeOtherTypeB',
            'SomeTypeC' => 'SomeOtherTypeC',
        ]);

        $actual = $map->typeFor($alias);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{0: string, 1: int|string, 2: Guid}>
     */
    public static function stringGuidProvider(): array
    {
        return [
            'integer id' => ['SomeTypeA', 99, Guid::fromInteger('SomeOtherTypeA', 99)],
            'string id' => ['SomeTypeC', 'foobar', Guid::fromString('SomeOtherTypeC', 'foobar')],
            'uuid' => ['SomeTypeB', self::UUID, Guid::fromUuid('SomeOtherTypeB', self::UUID)],
        ];
    }

    #[DataProvider('stringGuidProvider')]
    #[Test]
    public function it_returns_expected_guid(string $alias, int|string $id, Guid $expected): void
    {
        $map = new GuidTypeMap([
            'SomeTypeA' => 'SomeOtherTypeA',
            'SomeTypeB' => 'SomeOtherTypeB',
            'SomeTypeC' => 'SomeOtherTypeC',
        ]);

        $actual = $map->guidFor($alias, $id);

        $this->assertObjectEquals($expected, $actual);
    }

    /**
     * @return array<string, array{0: UnitEnum, 1: string|UnitEnum}>
     */
    public static function enumTypeProvider(): array
    {
        return [
            'backed enum to string' => [TestBackedEnum::Foo, 'SomeFooType'],
            'backed enum to enum' => [TestBackedEnum::Bar, TestBackedIntEnum::FooBar],
            'unit enum to string' => [TestUnitEnum::Baz, 'SomeBazType'],
            'unit enum to enum' => [TestUnitEnum::Bat, TestBackedIntEnum::BazBat],
        ];
    }

    #[DataProvider('enumTypeProvider')]
    #[Test]
    public function it_returns_expected_type_with_enums(UnitEnum $alias, string|UnitEnum $expected): void
    {
        $map = $this->makeEnumMap();

        $actual = $map->typeFor($alias);

        $this->assertSame($expected, $actual);
    }

    #[DataProvider('enumTypeProvider')]
    #[Test]
    public function it_returns_expected_guid_with_enums(UnitEnum $alias, string|UnitEnum $type): void
    {
        $map = $this->makeEnumMap();

        $actual = $map->guidFor($alias, self::UUID);

        $this->assertObjectEquals(Guid::fromUuid($type, self::UUID), $actual);
    }

    /**
     * @return array<string, array{0: string|UnitEnum, 1: string|UnitEnum}>
     */
    public static function defineProvider(): array
    {
        return [
            'string alias' => ['SomeAlias', 'SomeType'],
            'backed enum alias' => [TestBackedEnum::Foo, TestBackedIntEnum::FooBar],
            'unit enum alias' => [TestUnitEnum::Baz, 'SomeBazType'],
        ];
    }

    #[DataProvider('defineProvider')]
    #[Test]
    public function it_defines_a_type(string|UnitEnum $alias, string|UnitEnum $type): void
    {
        $map = new GuidTypeMap();

        $map->define($alias, $type);

        $this->assertSame($type, $map->typeFor($alias));
    }

    private function makeEnumMap(): GuidTypeMap
    {
        return new GuidTypeMap([
            TestBackedEnum::Foo->value => 'SomeFooType',
            TestBackedEnum::Bar->value => TestBackedIntEnum::FooBar,
            TestUnitEnum::Baz->name => 'SomeBazType',
            TestUnitEnum::Bat->name => TestBackedIntEnum::BazBat,
        ]);
    }
}
