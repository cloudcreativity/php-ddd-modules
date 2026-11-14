<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Infrastructure\Monolog;

use ArrayIterator;
use CloudCreativity\Modules\Contracts\Toolkit\Contextual;
use CloudCreativity\Modules\Infrastructure\Monolog\RecursiveParser;
use CloudCreativity\Modules\Infrastructure\Monolog\ValueParser;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class RecursiveParserTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function scalarProvider(): array
    {
        return [
            'null' => [null],
            'string' => ['foo'],
            'int' => [42],
            'float' => [1.5],
            'bool' => [true],
        ];
    }

    #[DataProvider('scalarProvider')]
    #[Test]
    public function it_returns_non_iterable_values_unchanged(mixed $value): void
    {
        $parser = new RecursiveParser();

        $actual = $parser->parse($value);

        $this->assertSame($value, $actual);
    }

    #[Test]
    public function it_returns_a_non_iterable_object_unchanged(): void
    {
        $value = new stdClass();
        $parser = new RecursiveParser();

        $actual = $parser->parse($value);

        $this->assertSame($value, $actual);
    }

    #[Test]
    public function it_parses_a_contextual_value(): void
    {
        $value = $this->createContextual('foo');
        $parser = new RecursiveParser();

        $actual = $parser->parse($value);

        $this->assertSame('foo', $actual);
    }

    #[Test]
    public function it_parses_a_contextual_value_that_returns_a_contextual_value(): void
    {
        $value = $this->createContextual($this->createContextual(['foo' => 'bar']));
        $parser = new RecursiveParser();

        $actual = $parser->parse($value);

        $this->assertSame(['foo' => 'bar'], $actual);
    }

    #[Test]
    public function it_recursively_parses_iterable_values(): void
    {
        $value = [
            'foo' => $this->createContextual(['bar' => $this->createContextual('baz')]),
            'bat' => new ArrayIterator(['a', $this->createContextual('b')]),
            'blah' => ['c', 'd'],
        ];

        $parser = new RecursiveParser();

        $actual = $parser->parse($value);

        $this->assertSame([
            'foo' => ['bar' => 'baz'],
            'bat' => ['a', 'b'],
            'blah' => ['c', 'd'],
        ], $actual);
    }

    #[Test]
    public function it_converts_a_generator_to_an_array(): void
    {
        $value = (function (): Generator {
            yield 'foo' => 'bar';
            yield 'baz' => $this->createContextual('bat');
        })();

        $parser = new RecursiveParser();

        $actual = $parser->parse($value);

        $this->assertSame(['foo' => 'bar', 'baz' => 'bat'], $actual);
    }

    #[Test]
    public function it_skips_iterable_values_that_do_not_have_string_or_integer_keys(): void
    {
        $value = (function (): Generator {
            yield 'foo' => 'bar';
            yield new stdClass() => 'skipped';
            yield 1.5 => 'also skipped';
            yield 99 => 'baz';
        })();

        $parser = new RecursiveParser();

        $actual = $parser->parse($value);

        $this->assertSame(['foo' => 'bar', 99 => 'baz'], $actual);
    }

    #[Test]
    public function it_does_not_sort_by_default(): void
    {
        $value = ['b' => ['z' => 1, 'y' => 2], 'a' => 3];
        $parser = new RecursiveParser();

        $actual = $parser->parse($value);

        $this->assertSame(['b' => ['z' => 1, 'y' => 2], 'a' => 3], $actual);
    }

    #[Test]
    public function it_recursively_sorts_by_key(): void
    {
        $value = [
            'b' => ['z' => 1, 'y' => 2],
            'a' => $this->createContextual(['d' => 4, 'c' => 3]),
        ];

        $parser = new RecursiveParser(sorted: true);

        $actual = $parser->parse($value);

        $this->assertSame([
            'a' => ['c' => 3, 'd' => 4],
            'b' => ['y' => 2, 'z' => 1],
        ], $actual);
    }

    #[Test]
    public function it_uses_the_inner_parser_for_non_iterable_values(): void
    {
        $value = [
            'foo' => 'bar',
            'baz' => ['bat' => $this->createContextual('blah')],
            'int' => 1,
        ];

        $inner = $this->createStub(ValueParser::class);
        $inner
            ->method('parse')
            ->willReturnCallback(fn (mixed $value): mixed => is_string($value) ? strtoupper($value) : $value);

        $parser = new RecursiveParser(innerParser: $inner);

        $actual = $parser->parse($value);

        $this->assertSame([
            'foo' => 'BAR',
            'baz' => ['bat' => 'BLAH'],
            'int' => 1,
        ], $actual);
    }

    #[Test]
    public function it_does_not_pass_iterable_values_to_the_inner_parser(): void
    {
        $value = ['foo' => ['bar' => 'baz']];

        $inner = $this->createMock(ValueParser::class);
        $inner
            ->expects($this->once())
            ->method('parse')
            ->with('baz')
            ->willReturn('BAZ');

        $parser = new RecursiveParser(innerParser: $inner);

        $actual = $parser->parse($value);

        $this->assertSame(['foo' => ['bar' => 'BAZ']], $actual);
    }

    private function createContextual(mixed $context): Contextual
    {
        return new readonly class ($context) implements Contextual {
            public function __construct(private mixed $value)
            {
            }

            public function context(): mixed
            {
                return $this->value;
            }
        };
    }
}
