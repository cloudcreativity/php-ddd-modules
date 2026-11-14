<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Infrastructure\Queue;

use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Infrastructure\InfrastructureException;
use CloudCreativity\Modules\Infrastructure\Queue\Enqueuer;
use CloudCreativity\Modules\Infrastructure\Queue\EnqueuerContainer;
use CloudCreativity\Modules\Testing\FakeContainer;
use CloudCreativity\Modules\Tests\Unit\Application\Bus\TestCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnqueuerContainerTest extends TestCase
{
    /**
     * @return array<string, array{class-string<Command>}>
     */
    public static function multipleCommandsProvider(): array
    {
        return [
            'first command' => [TestCommand::class],
            'second command' => [TestQueueableCommand::class],
        ];
    }

    #[Test]
    public function it_gets_an_enqueuer_bound_with_a_closure(): void
    {
        $enqueuer = new class () extends TestEnqueuer {};
        $container = new EnqueuerContainer();
        $container->bind(TestQueueableCommand::class, fn () => $enqueuer);

        $actual = $container->get(TestQueueableCommand::class);

        $this->assertEquals(new Enqueuer($enqueuer), $actual);
    }

    #[Test]
    public function it_gets_the_closure_default_enqueuer_when_the_command_is_not_bound(): void
    {
        $default = new class () extends TestEnqueuer {};
        $container = new EnqueuerContainer(fn () => $default);

        $actual = $container->get(TestQueueableCommand::class);

        $this->assertEquals(new Enqueuer($default), $actual);
    }

    #[Test]
    public function it_prefers_a_closure_binding_over_the_default_enqueuer(): void
    {
        $bound = new class () extends TestEnqueuer {};
        $container = new EnqueuerContainer(fn () => $this->fail('Default enqueuer resolved.'));
        $container->bind(TestQueueableCommand::class, fn () => $bound);

        $actual = $container->get(TestQueueableCommand::class);

        $this->assertEquals(new Enqueuer($bound), $actual);
    }

    #[Test]
    public function it_gets_an_enqueuer_bound_with_a_string_from_the_psr_container(): void
    {
        $enqueuer = new class () extends TestEnqueuer {};
        $psrContainer = new FakeContainer();
        $psrContainer->instance(TestEnqueuer::class, $enqueuer);
        $container = new EnqueuerContainer(container: $psrContainer);
        $container->bind(TestQueueableCommand::class, TestEnqueuer::class);

        $actual = $container->get(TestQueueableCommand::class);

        $this->assertEquals(new Enqueuer($enqueuer), $actual);
    }

    #[Test]
    public function it_gets_the_psr_container_default_enqueuer_when_the_command_is_not_bound(): void
    {
        $default = new class () extends TestEnqueuer {};
        $psrContainer = new FakeContainer();
        $psrContainer->instance('DefaultEnqueuer', $default);
        $container = new EnqueuerContainer(default: 'DefaultEnqueuer', container: $psrContainer);

        $actual = $container->get(TestQueueableCommand::class);

        $this->assertEquals(new Enqueuer($default), $actual);
    }

    #[Test]
    public function it_prefers_a_psr_container_binding_over_the_default_enqueuer(): void
    {
        $bound = new class () extends TestEnqueuer {};
        $psrContainer = new FakeContainer();
        $psrContainer->instance(TestEnqueuer::class, $bound);
        $psrContainer->bind('DefaultEnqueuer', fn () => $this->fail('Default enqueuer resolved.'));
        $container = new EnqueuerContainer(default: 'DefaultEnqueuer', container: $psrContainer);
        $container->bind(TestQueueableCommand::class, TestEnqueuer::class);

        $actual = $container->get(TestQueueableCommand::class);

        $this->assertEquals(new Enqueuer($bound), $actual);
    }

    /**
     * @param class-string<Command> $command
     */
    #[DataProvider('multipleCommandsProvider')]
    #[Test]
    public function it_binds_multiple_commands_to_one_enqueuer(string $command): void
    {
        $enqueuer = new class () extends TestEnqueuer {};
        $container = new EnqueuerContainer();
        $container->bind([TestCommand::class, TestQueueableCommand::class], fn () => $enqueuer);

        $actual = $container->get($command);

        $this->assertEquals(new Enqueuer($enqueuer), $actual);
    }

    #[Test]
    public function it_throws_when_the_default_enqueuer_is_a_string_without_a_psr_container(): void
    {
        $this->expectException(InfrastructureException::class);
        $this->expectExceptionMessage('Cannot bind default enqueuer as a string without a PSR container.');

        new EnqueuerContainer(default: TestEnqueuer::class);
    }

    #[Test]
    public function it_throws_when_there_is_no_binding_and_no_default_enqueuer(): void
    {
        $container = new EnqueuerContainer();
        $container->bind(TestCommand::class, fn () => new class () extends TestEnqueuer {});

        $this->expectException(InfrastructureException::class);
        $this->expectExceptionMessage('No enqueuer bound for command: ' . TestQueueableCommand::class);

        $container->get(TestQueueableCommand::class);
    }

    #[Test]
    public function it_uses_a_closure_default_enqueuer_set_after_construction(): void
    {
        $default = new class () extends TestEnqueuer {};
        $container = new EnqueuerContainer();

        $container->withDefault(fn () => $default);
        $actual = $container->get(TestQueueableCommand::class);

        $this->assertEquals(new Enqueuer($default), $actual);
    }

    #[Test]
    public function it_uses_a_psr_container_default_enqueuer_set_after_construction(): void
    {
        $default = new class () extends TestEnqueuer {};
        $psrContainer = new FakeContainer();
        $psrContainer->instance(TestEnqueuer::class, $default);
        $container = new EnqueuerContainer(container: $psrContainer);

        $container->withDefault(TestEnqueuer::class);
        $actual = $container->get(TestQueueableCommand::class);

        $this->assertEquals(new Enqueuer($default), $actual);
    }

    #[Test]
    public function it_prefers_a_binding_over_a_default_enqueuer_set_after_construction(): void
    {
        $bound = new class () extends TestEnqueuer {};
        $default = new class () extends TestEnqueuer {};
        $container = new EnqueuerContainer();
        $container->bind(TestQueueableCommand::class, fn () => $bound);

        $container->withDefault(fn () => $default);
        $actual = $container->get(TestQueueableCommand::class);

        $this->assertEquals(new Enqueuer($bound), $actual);
    }

    #[Test]
    public function it_throws_when_the_default_enqueuer_is_already_set_via_the_constructor(): void
    {
        $container = new EnqueuerContainer(default: fn () => new class () extends TestEnqueuer {});

        $this->expectException(InfrastructureException::class);
        $this->expectExceptionMessage('Default enqueuer is already set.');

        $container->withDefault(fn () => new class () extends TestEnqueuer {});
    }

    #[Test]
    public function it_throws_when_the_default_enqueuer_is_already_set_via_with_default(): void
    {
        $container = new EnqueuerContainer();
        $container->withDefault(fn () => new class () extends TestEnqueuer {});

        $this->expectException(InfrastructureException::class);
        $this->expectExceptionMessage('Default enqueuer is already set.');

        $container->withDefault(fn () => new class () extends TestEnqueuer {});
    }

    #[Test]
    public function it_throws_when_setting_a_string_default_enqueuer_without_a_psr_container(): void
    {
        $container = new EnqueuerContainer();

        $this->expectException(InfrastructureException::class);
        $this->expectExceptionMessage('Cannot bind default enqueuer as a string without a PSR container.');

        $container->withDefault(TestEnqueuer::class);
    }
}
