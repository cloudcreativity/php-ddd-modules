<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Toolkit;

use CloudCreativity\Modules\Tests\Unit\Toolkit\Identifiers\TestIdentifier;
use CloudCreativity\Modules\Toolkit\ModuleBasename;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ModuleBasenameTest extends TestCase
{
    /**
     * @return array<array<string>>
     */
    public static function moduleProvider(): array
    {
        return [
            [
                'App\Modules\WaitList\BoundedContext\Application\Commands\ScheduleDelayedProcess\ScheduleDelayedProcessCommand',
                'WaitList',
                'ScheduleDelayedProcessCommand',
            ],
            [
                'App\Modules\WaitList\Application\Commands\ScheduleDelayedProcess\ScheduleDelayedProcessCommand',
                'WaitList',
                'ScheduleDelayedProcessCommand',
            ],
            [
                'App\Modules\WaitList\BoundedContext\Application\Queries\GetScheduledProcessList\GetScheduledProcessListQuery',
                'WaitList',
                'GetScheduledProcessListQuery',
            ],
            [
                'App\Modules\WaitList\Application\Queries\GetScheduledProcessList\GetScheduledProcessListQuery',
                'WaitList',
                'GetScheduledProcessListQuery',
            ],
            [
                'Vendor\Modules\Podcasts\Shared\IntegrationEvents\PodcastPublished',
                'Podcasts',
                'PodcastPublished',
            ],
            [
                'Vendor\Modules\Podcasts\Shared\IntegrationEvents\PodcastPublished',
                'Podcasts',
                'PodcastPublished',
            ],
            [
                'App\Modules\WaitList\BoundedContext\Domain\Events\TicketsWereReleased',
                'WaitList',
                'TicketsWereReleased',
            ],
            [
                'App\Modules\WaitList\BoundedContext\Infrastructure\Queue\ProcessWaitList\ProcessWaitListJob',
                'WaitList',
                'ProcessWaitListJob',
            ],
            [
                'App\Modules\WaitList\Infrastructure\Queue\ProcessWaitList\ProcessWaitListJob',
                'WaitList',
                'ProcessWaitListJob',
            ],
            [
                'Foo\Bar\Modules\Ordering\BoundedContext\Baz\Bat\FooBarCommand',
                'Ordering',
                'FooBarCommand',
            ],
            [
                'App\Modules\BatchMailer\BoundedContext\Application\Commands\SendEmail\SendEmailCommand',
                'BatchMailer',
                'SendEmailCommand',
            ],
            [
                'App\Modules\BatchMailer\Application\Commands\SendEmail\SendEmailCommand',
                'BatchMailer',
                'SendEmailCommand',
            ],
            [
                'App\Modules\BatchMailer\Infrastructure\Queue\SendEmail\SendEmailJob',
                'BatchMailer',
                'SendEmailJob',
            ],
        ];
    }

    /**
     * Test module names when modules are not in use.
     *
     * This scenario happens when an application only has one bounded context,
     * i.e. one module. For example, a microservice that represents that bounded context.
     *
     * @return array<array<string>>
     */
    public static function withoutModuleProvider(): array
    {
        return [
            [
                'App\BoundedContext\Application\Queries\GetTradeIn\GetTradeInQuery',
                'GetTradeInQuery',
            ],
            [
                'App\BoundedContext\Application\Commands\SubmitTradeIn\SubmitTradeInCommand',
                'SubmitTradeInCommand',
            ],
            [
                'App\BoundedContext\Domain\Events\TradeInSubmitted',
                'TradeInSubmitted',
            ],
            [
                'App\BoundedContext\Shared\IntegrationEvents\TradeInSubmitted',
                'TradeInSubmitted',
            ],
        ];
    }

    #[DataProvider('moduleProvider')]
    #[Test]
    public function it_parses_a_class_name_with_a_module(string $value, string $context, string $message): void
    {
        $name = ModuleBasename::from($value);

        $this->assertSame($context, $name->module);
        $this->assertSame($message, $name->name);
    }

    #[DataProvider('moduleProvider')]
    #[Test]
    public function it_tries_to_parse_a_class_name_with_a_module(string $value, string $context, string $message): void
    {
        $name = ModuleBasename::tryFrom($value);

        $this->assertNotNull($name);
        $this->assertSame($context, $name->module);
        $this->assertSame($message, $name->name);
    }

    #[DataProvider('moduleProvider')]
    #[Test]
    public function it_converts_a_name_with_a_module_to_a_string(string $value, string $context, string $message): void
    {
        $name = ModuleBasename::from($value);

        $actual = $name->toString();

        $this->assertSame("{$context}:{$message}", $actual);
    }

    #[DataProvider('moduleProvider')]
    #[Test]
    public function it_converts_a_name_with_a_module_to_a_string_with_a_delimiter(
        string $value,
        string $context,
        string $message,
    ): void {
        $name = ModuleBasename::from($value);

        $actual = $name->toString('/');

        $this->assertSame("{$context}/{$message}", $actual);
    }

    #[DataProvider('moduleProvider')]
    #[Test]
    public function it_casts_a_name_with_a_module_to_a_string(string $value, string $context, string $message): void
    {
        $name = ModuleBasename::from($value);

        $actual = (string) $name;

        $this->assertSame("{$context}:{$message}", $actual);
    }

    #[DataProvider('withoutModuleProvider')]
    #[Test]
    public function it_parses_a_class_name_without_a_module(string $value, string $message): void
    {
        $name = ModuleBasename::from($value);

        $this->assertNull($name->module);
        $this->assertSame($message, $name->name);
    }

    #[DataProvider('withoutModuleProvider')]
    #[Test]
    public function it_tries_to_parse_a_class_name_without_a_module(string $value, string $message): void
    {
        $name = ModuleBasename::tryFrom($value);

        $this->assertNotNull($name);
        $this->assertNull($name->module);
        $this->assertSame($message, $name->name);
    }

    #[DataProvider('withoutModuleProvider')]
    #[Test]
    public function it_converts_a_name_without_a_module_to_a_string(string $value, string $message): void
    {
        $name = ModuleBasename::from($value);

        $actual = $name->toString();

        $this->assertSame($message, $actual);
    }

    #[DataProvider('withoutModuleProvider')]
    #[Test]
    public function it_ignores_the_delimiter_when_converting_a_name_without_a_module_to_a_string(
        string $value,
        string $message,
    ): void {
        $name = ModuleBasename::from($value);

        $actual = $name->toString('/');

        $this->assertSame($message, $actual);
    }

    #[DataProvider('withoutModuleProvider')]
    #[Test]
    public function it_casts_a_name_without_a_module_to_a_string(string $value, string $message): void
    {
        $name = ModuleBasename::from($value);

        $actual = (string) $name;

        $this->assertSame($message, $actual);
    }

    #[Test]
    public function it_converts_to_an_array(): void
    {
        $value = ModuleBasename::from(
            'CloudCreativity\Application\Modules\WaitList\BoundedContext\Application\Commands\ScheduleDelayedProcess\ScheduleDelayedProcessCommand',
        );

        $actual = $value->toArray();

        $this->assertSame([
            'module' => 'WaitList',
            'name' => 'ScheduleDelayedProcessCommand',
        ], $actual);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $value = ModuleBasename::from(
            'CloudCreativity\Application\Modules\WaitList\BoundedContext\Application\Commands\ScheduleDelayedProcess\ScheduleDelayedProcessCommand',
        );

        $actual = json_encode($value, JSON_THROW_ON_ERROR);

        $this->assertJsonStringEqualsJsonString(
            '{"module":"WaitList","name":"ScheduleDelayedProcessCommand"}',
            $actual,
        );
    }

    #[Test]
    public function it_returns_null_when_trying_from_an_invalid_value(): void
    {
        $name = ModuleBasename::tryFrom(ModuleBasename::class);

        $this->assertNull($name);
    }

    #[Test]
    public function it_throws_when_parsing_an_invalid_value(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unexpected fully-qualified message class name.');

        ModuleBasename::from(ModuleBasename::class);
    }

    #[Test]
    public function it_parses_an_object(): void
    {
        $object = new TestIdentifier('foo');

        $actual = ModuleBasename::from($object);

        $this->assertSame(['module' => 'Tests', 'name' => 'TestIdentifier'], $actual->toArray());
    }
}
