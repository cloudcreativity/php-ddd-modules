<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\Bus;

use CloudCreativity\Modules\Bus\BusException;
use CloudCreativity\Modules\Bus\CommandHandler;
use CloudCreativity\Modules\Bus\CommandHandlerContainer;
use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Testing\FakeContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandHandlerContainerTest extends TestCase
{
    #[Test]
    public function it_resolves_using_closure_bindings(): void
    {
        $a = new class () extends TestCommandHandler {};
        $b = $this->createStub(TestCommandHandler::class);

        $command1 = new class () implements Command {};
        $command2 = new class () implements Command {};

        $container = new CommandHandlerContainer();
        $container->bind($command1::class, fn () => $a);
        $container->bind($command2::class, fn () => $b);

        $this->assertEquals(new CommandHandler($a), $container->get($command1::class));
        $this->assertEquals(new CommandHandler($b), $container->get($command2::class));
    }

    #[Test]
    public function it_throws_when_no_closure_binding_exists_for_the_command(): void
    {
        $command1 = new class () implements Command {};
        $command2 = new class () implements Command {};

        $container = new CommandHandlerContainer();
        $container->bind($command1::class, fn () => new class () extends TestCommandHandler {});

        $this->expectException(BusException::class);
        $this->expectExceptionMessage('No command handler bound for command class: ' . $command2::class);

        $container->get($command2::class);
    }

    #[Test]
    public function it_resolves_via_psr_container(): void
    {
        $a = new class () extends TestCommandHandler {};
        $b = $this->createStub(TestCommandHandler::class);

        $command1 = new class () implements Command {};
        $command2 = new class () implements Command {};

        $psrContainer = new FakeContainer();
        $psrContainer->instance($a::class, $a);
        $psrContainer->instance($b::class, $b);

        $container = new CommandHandlerContainer($psrContainer);
        $container->bind($command1::class, $a::class);
        $container->bind($command2::class, $b::class);

        $this->assertEquals(new CommandHandler($a), $container->get($command1::class));
        $this->assertEquals(new CommandHandler($b), $container->get($command2::class));
    }

    #[Test]
    public function it_throws_when_no_psr_container_binding_exists_for_the_command(): void
    {
        $command1 = new class () implements Command {};
        $command2 = new class () implements Command {};

        $psrContainer = new FakeContainer();
        $psrContainer->instance(TestCommandHandler::class, new class () extends TestCommandHandler {});

        $container = new CommandHandlerContainer($psrContainer);
        $container->bind($command1::class, TestCommandHandler::class);

        $this->expectException(BusException::class);
        $this->expectExceptionMessage('No command handler bound for command class: ' . $command2::class);

        $container->get($command2::class);
    }

    #[Test]
    public function it_throws_when_binding_a_string_without_a_psr_container(): void
    {
        $container = new CommandHandlerContainer();

        $this->expectException(BusException::class);
        $this->expectExceptionMessage('Cannot use a string command handler binding without a PSR container.');

        $container->bind(TestCommand::class, TestCommandHandler::class);
    }
}
