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

use CloudCreativity\Modules\Contracts\Toolkit\Contextual;
use CloudCreativity\Modules\Infrastructure\Monolog\ContextualProcessor;
use CloudCreativity\Modules\Infrastructure\Monolog\ValueParser;
use DateTimeImmutable;
use LogicException;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContextualProcessorTest extends TestCase
{
    #[Test]
    public function it_replaces_contextual_values_with_their_context(): void
    {
        $record = $this->createRecord([
            'foo' => $this->createContextual(['bar' => 'baz']),
            'bat' => 'foobar',
            'nested' => ['value' => $nested = $this->createContextual('blah!')],
        ]);

        $processor = new ContextualProcessor();
        $actual = $processor($record);

        $this->assertEquals($record->with(context: [
            'foo' => ['bar' => 'baz'],
            'bat' => 'foobar',
            'nested' => ['value' => $nested],
        ]), $actual);
    }

    #[Test]
    public function it_does_not_modify_the_exception_value(): void
    {
        $exception = new LogicException('Boom!');
        $record = $this->createRecord(['exception' => $exception]);

        $processor = new ContextualProcessor(recursive: true);
        $actual = $processor($record);

        $this->assertSame(['exception' => $exception], $actual->context);
    }

    #[Test]
    public function it_does_not_unwrap_a_contextual_exception_value(): void
    {
        $exception = $this->createContextual('unwrapped');
        $record = $this->createRecord(['exception' => $exception]);

        $processor = new ContextualProcessor();
        $actual = $processor($record);

        $this->assertSame(['exception' => $exception], $actual->context);
    }

    #[Test]
    public function it_parses_values_using_the_provided_parser(): void
    {
        $record = $this->createRecord([
            'foo' => $this->createContextual('bar'),
            'baz' => 'bat',
            'exception' => 'not parsed',
        ]);

        $parser = $this->createStub(ValueParser::class);
        $parser
            ->method('parse')
            ->willReturnCallback(fn (mixed $value): mixed => is_string($value) ? strtoupper($value) : $value);

        $processor = new ContextualProcessor(parser: $parser);
        $actual = $processor($record);

        $this->assertSame([
            'foo' => 'BAR',
            'baz' => 'BAT',
            'exception' => 'not parsed',
        ], $actual->context);
    }

    #[Test]
    public function it_recursively_parses_values(): void
    {
        $record = $this->createRecord([
            'foo' => $this->createContextual([
                'b' => $this->createContextual(['y' => 2, 'x' => 1]),
                'a' => 'value',
            ]),
            'bar' => ['nested' => $this->createContextual('blah!')],
        ]);

        $processor = new ContextualProcessor(recursive: true);
        $actual = $processor($record);

        $this->assertSame([
            'foo' => [
                'b' => ['y' => 2, 'x' => 1],
                'a' => 'value',
            ],
            'bar' => ['nested' => 'blah!'],
        ], $actual->context);
    }

    #[Test]
    public function it_recursively_parses_and_sorts_values(): void
    {
        $record = $this->createRecord([
            'foo' => $this->createContextual([
                'b' => $this->createContextual(['y' => 2, 'x' => 1]),
                'a' => 'value',
            ]),
        ]);

        $processor = new ContextualProcessor(recursive: true, sorted: true);
        $actual = $processor($record);

        $this->assertSame([
            'foo' => [
                'a' => 'value',
                'b' => ['x' => 1, 'y' => 2],
            ],
        ], $actual->context);
    }

    #[Test]
    public function it_recursively_parses_values_using_the_provided_parser(): void
    {
        $record = $this->createRecord([
            'foo' => $this->createContextual(['bar' => 'baz', 'bat' => ['blah']]),
        ]);

        $parser = $this->createStub(ValueParser::class);
        $parser
            ->method('parse')
            ->willReturnCallback(fn (mixed $value): mixed => is_string($value) ? strtoupper($value) : $value);

        $processor = new ContextualProcessor(recursive: true, parser: $parser);
        $actual = $processor($record);

        $this->assertSame([
            'foo' => ['bar' => 'BAZ', 'bat' => ['BLAH']],
        ], $actual->context);
    }

    #[Test]
    public function it_does_not_modify_the_rest_of_the_record(): void
    {
        $record = new LogRecord(
            datetime: new DateTimeImmutable('2026-01-01 12:00:00'),
            channel: 'my-channel',
            level: Level::Warning,
            message: 'Hello World!',
            context: ['foo' => $this->createContextual('bar')],
            extra: ['baz' => 'bat'],
        );

        $processor = new ContextualProcessor();
        $actual = $processor($record);

        $this->assertEquals(new LogRecord(
            datetime: new DateTimeImmutable('2026-01-01 12:00:00'),
            channel: 'my-channel',
            level: Level::Warning,
            message: 'Hello World!',
            context: ['foo' => 'bar'],
            extra: ['baz' => 'bat'],
        ), $actual);
    }

    /**
     * @param array<mixed> $context
     */
    private function createRecord(array $context): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Debug,
            message: 'Test message.',
            context: $context,
        );
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
