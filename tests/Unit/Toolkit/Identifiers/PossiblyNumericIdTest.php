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

use CloudCreativity\Modules\Toolkit\Identifiers\IntegerId;
use CloudCreativity\Modules\Toolkit\Identifiers\PossiblyNumericId;
use CloudCreativity\Modules\Toolkit\Identifiers\StringId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PossiblyNumericIdTest extends TestCase
{
    /**
     * @return array<string, array{0: int|string, 1: int|string}>
     */
    public static function valueProvider(): array
    {
        return [
            'integer' => [1, 1],
            'numeric string' => ['1', 1],
            'numeric string with leading zeros' => ['0001', 1],
            'larger numeric string' => ['999', 999],
            'non-numeric string' => ['foobar', 'foobar'],
            'numeric string with whitespace' => [' 0009 ', ' 0009 '],
        ];
    }

    #[DataProvider('valueProvider')]
    #[Test]
    public function it_converts_numeric_strings_to_integers(int|string $value, int|string $expected): void
    {
        $actual = new PossiblyNumericId($value);

        $this->assertSame($expected, $actual->value);
        $this->assertSame((string) $expected, $actual->toString());
        $this->assertSame((string) $expected, (string) $actual);
    }

    #[DataProvider('valueProvider')]
    #[Test]
    public function it_is_created_using_from(int|string $value, int|string $expected): void
    {
        $actual = PossiblyNumericId::from($value);

        $this->assertSame($expected, $actual->value);
    }

    #[DataProvider('valueProvider')]
    #[Test]
    public function it_converts_to_an_identifier(int|string $value, int|string $expected): void
    {
        $id = new PossiblyNumericId($value);

        $actual = $id->toId();

        $this->assertObjectEquals(is_string($expected) ? new StringId($expected) : new IntegerId($expected), $actual);
    }

    #[DataProvider('valueProvider')]
    #[Test]
    public function it_serializes_to_json(int|string $value, int|string $expected): void
    {
        $id = new PossiblyNumericId($value);

        $actual = json_encode(['value' => $id], JSON_THROW_ON_ERROR);

        $this->assertJsonStringEqualsJsonString(json_encode(['value' => $expected], JSON_THROW_ON_ERROR), $actual);
    }
}
