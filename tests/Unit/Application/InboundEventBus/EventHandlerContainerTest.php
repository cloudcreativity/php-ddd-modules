<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\InboundEventBus;

use CloudCreativity\Modules\Bus\BusException;
use CloudCreativity\Modules\Bus\EventHandler;
use CloudCreativity\Modules\Bus\EventHandlerContainer;
use CloudCreativity\Modules\Testing\FakeContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventHandlerContainerTest extends TestCase
{
    #[Test]
    public function it_has_handler_bindings(): void
    {
        $a = new class () extends TestEventHandler {};
        $b = $this->createStub(TestEventHandler::class);

        $container = new EventHandlerContainer();
        $container->bind(TestInboundEvent::class, fn () => $a);
        $container->bind(TestOtherInboundEvent::class, fn () => $b);

        $this->assertEquals(new EventHandler($a), $container->get(TestInboundEvent::class));
        $this->assertEquals(new EventHandler($b), $container->get(TestOtherInboundEvent::class));
    }

    #[Test]
    public function it_uses_psr_container(): void
    {
        $a = new class () extends TestEventHandler {};
        $b = $this->createStub(TestEventHandler::class);

        $psrContainer = new FakeContainer();
        $psrContainer->instance($a::class, $a);
        $psrContainer->instance($b::class, $b);

        $container = new EventHandlerContainer(container: $psrContainer);
        $container->bind(TestInboundEvent::class, $a::class);
        $container->bind(TestOtherInboundEvent::class, $b::class);

        $this->assertEquals(new EventHandler($a), $container->get(TestInboundEvent::class));
        $this->assertEquals(new EventHandler($b), $container->get(TestOtherInboundEvent::class));
    }

    #[Test]
    public function it_prefers_a_bound_handler_over_the_default_handler(): void
    {
        $handler = new class () extends TestEventHandler {};
        $container = new EventHandlerContainer(default: fn () => new class () extends TestEventHandler {});
        $container->bind(TestInboundEvent::class, fn () => $handler);

        $actual = $container->get(TestInboundEvent::class);

        $this->assertEquals(new EventHandler($handler), $actual);
    }

    #[Test]
    public function it_has_bound_default_handler(): void
    {
        $default = $this->createStub(TestEventHandler::class);
        $container = new EventHandlerContainer(default: fn () => $default);
        $container->bind(TestInboundEvent::class, fn () => new class () extends TestEventHandler {});

        $actual = $container->get(TestOtherInboundEvent::class);

        $this->assertEquals(new EventHandler($default), $actual);
    }

    #[Test]
    public function it_has_default_handler_in_psr_container(): void
    {
        $default = $this->createStub(TestEventHandler::class);
        $psrContainer = new FakeContainer();
        $psrContainer->instance('default-handler', $default);
        $container = new EventHandlerContainer(default: 'default-handler', container: $psrContainer);
        $container->bind(TestInboundEvent::class, fn () => new class () extends TestEventHandler {});

        $actual = $container->get(TestOtherInboundEvent::class);

        $this->assertEquals(new EventHandler($default), $actual);
    }

    #[Test]
    public function it_does_not_have_handler(): void
    {
        $container = new EventHandlerContainer();
        $container->bind(TestInboundEvent::class, fn () => new class () extends TestEventHandler {});

        $this->expectException(BusException::class);
        $this->expectExceptionMessage(
            'No handler bound for integration event: ' . TestOtherInboundEvent::class,
        );

        $container->get(TestOtherInboundEvent::class);
    }

    #[Test]
    public function it_throws_when_binding_a_string_without_a_psr_container(): void
    {
        $container = new EventHandlerContainer();

        $this->expectException(BusException::class);
        $this->expectExceptionMessage('Cannot use a string event handler binding without a PSR container.');

        $container->bind(TestInboundEvent::class, TestEventHandler::class);
    }

    #[Test]
    public function it_binds_a_default_handler(): void
    {
        $handler = new class () extends TestEventHandler {};
        $container = new EventHandlerContainer();
        $container->withDefault(fn () => $handler);

        $actual = $container->get(TestInboundEvent::class);

        $this->assertEquals(new EventHandler($handler), $actual);
    }

    #[Test]
    public function it_throws_when_the_default_handler_is_already_set(): void
    {
        $container = new EventHandlerContainer(default: fn () => new class () extends TestEventHandler {});

        $this->expectException(BusException::class);
        $this->expectExceptionMessage('Default event handler binding is already set.');

        $container->withDefault(fn () => new class () extends TestEventHandler {});
    }
}
