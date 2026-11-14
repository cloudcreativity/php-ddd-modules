<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Infrastructure\OutboundEventBus;

use CloudCreativity\Modules\Infrastructure\InfrastructureException;
use CloudCreativity\Modules\Infrastructure\OutboundEventBus\PublisherHandler;
use CloudCreativity\Modules\Infrastructure\OutboundEventBus\PublisherHandlerContainer;
use CloudCreativity\Modules\Testing\FakeContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PublisherHandlerContainerTest extends TestCase
{
    #[Test]
    public function it_gets_a_handler_bound_with_a_closure(): void
    {
        $publisher = new class () extends TestPublisher {};
        $container = new PublisherHandlerContainer();
        $container->bind(TestNamedOutboundEvent::class, fn () => $publisher);

        $actual = $container->get(TestNamedOutboundEvent::class);

        $this->assertEquals(new PublisherHandler($publisher), $actual);
    }

    #[Test]
    public function it_gets_the_closure_default_handler_when_the_event_is_not_bound(): void
    {
        $default = new class () extends TestPublisher {};
        $container = new PublisherHandlerContainer(default: fn () => $default);

        $actual = $container->get(TestNamedOutboundEvent::class);

        $this->assertEquals(new PublisherHandler($default), $actual);
    }

    #[Test]
    public function it_prefers_a_closure_binding_over_the_default_handler(): void
    {
        $bound = new class () extends TestPublisher {};
        $container = new PublisherHandlerContainer(default: fn () => $this->fail('Default handler resolved.'));
        $container->bind(TestNamedOutboundEvent::class, fn () => $bound);

        $actual = $container->get(TestNamedOutboundEvent::class);

        $this->assertEquals(new PublisherHandler($bound), $actual);
    }

    #[Test]
    public function it_gets_a_handler_bound_with_a_string_from_the_psr_container(): void
    {
        $publisher = new class () extends TestPublisher {};
        $psrContainer = new FakeContainer();
        $psrContainer->instance(TestPublisher::class, $publisher);
        $container = new PublisherHandlerContainer(container: $psrContainer);
        $container->bind(TestNamedOutboundEvent::class, TestPublisher::class);

        $actual = $container->get(TestNamedOutboundEvent::class);

        $this->assertEquals(new PublisherHandler($publisher), $actual);
    }

    #[Test]
    public function it_gets_the_psr_container_default_handler_when_the_event_is_not_bound(): void
    {
        $default = new class () extends TestPublisher {};
        $psrContainer = new FakeContainer();
        $psrContainer->instance('DefaultPublisher', $default);
        $container = new PublisherHandlerContainer(default: 'DefaultPublisher', container: $psrContainer);

        $actual = $container->get(TestNamedOutboundEvent::class);

        $this->assertEquals(new PublisherHandler($default), $actual);
    }

    #[Test]
    public function it_prefers_a_psr_container_binding_over_the_default_handler(): void
    {
        $bound = new class () extends TestPublisher {};
        $psrContainer = new FakeContainer();
        $psrContainer->instance(TestPublisher::class, $bound);
        $psrContainer->bind('DefaultPublisher', fn () => $this->fail('Default handler resolved.'));
        $container = new PublisherHandlerContainer(default: 'DefaultPublisher', container: $psrContainer);
        $container->bind(TestNamedOutboundEvent::class, TestPublisher::class);

        $actual = $container->get(TestNamedOutboundEvent::class);

        $this->assertEquals(new PublisherHandler($bound), $actual);
    }

    #[Test]
    public function it_throws_when_the_default_handler_is_a_string_without_a_psr_container(): void
    {
        $this->expectException(InfrastructureException::class);
        $this->expectExceptionMessage(
            'Cannot bind default event publisher handler as a string without a PSR container.',
        );

        new PublisherHandlerContainer(default: TestPublisher::class);
    }

    #[Test]
    public function it_throws_when_binding_a_string_without_a_psr_container(): void
    {
        $container = new PublisherHandlerContainer();

        $this->expectException(InfrastructureException::class);
        $this->expectExceptionMessage('Cannot bind event publisher handler as a string without a PSR container.');

        $container->bind(TestNamedOutboundEvent::class, TestPublisher::class);
    }

    #[Test]
    public function it_throws_when_there_is_no_binding_and_no_default_handler(): void
    {
        $container = new PublisherHandlerContainer();
        $container->bind(TestOutboundEvent::class, fn () => new class () extends TestPublisher {});

        $this->expectException(InfrastructureException::class);
        $this->expectExceptionMessage('No handler bound for integration event: ' . TestNamedOutboundEvent::class);

        $container->get(TestNamedOutboundEvent::class);
    }

    #[Test]
    public function it_throws_when_there_is_no_binding_and_no_default_handler_in_the_psr_container(): void
    {
        $psrContainer = new FakeContainer();
        $psrContainer->instance(TestPublisher::class, new class () extends TestPublisher {});
        $container = new PublisherHandlerContainer(container: $psrContainer);
        $container->bind(TestOutboundEvent::class, TestPublisher::class);

        $this->expectException(InfrastructureException::class);
        $this->expectExceptionMessage('No handler bound for integration event: ' . TestNamedOutboundEvent::class);

        $container->get(TestNamedOutboundEvent::class);
    }

    #[Test]
    public function it_uses_a_closure_default_handler_set_after_construction(): void
    {
        $default = new class () extends TestPublisher {};
        $container = new PublisherHandlerContainer();

        $container->withDefault(fn () => $default);
        $actual = $container->get(TestNamedOutboundEvent::class);

        $this->assertEquals(new PublisherHandler($default), $actual);
    }

    #[Test]
    public function it_uses_a_psr_container_default_handler_set_after_construction(): void
    {
        $default = new class () extends TestPublisher {};
        $psrContainer = new FakeContainer();
        $psrContainer->instance(TestPublisher::class, $default);
        $container = new PublisherHandlerContainer(container: $psrContainer);

        $container->withDefault(TestPublisher::class);
        $actual = $container->get(TestNamedOutboundEvent::class);

        $this->assertEquals(new PublisherHandler($default), $actual);
    }

    #[Test]
    public function it_prefers_a_binding_over_a_default_handler_set_after_construction(): void
    {
        $bound = new class () extends TestPublisher {};
        $default = new class () extends TestPublisher {};
        $container = new PublisherHandlerContainer();
        $container->bind(TestNamedOutboundEvent::class, fn () => $bound);

        $container->withDefault(fn () => $default);
        $actual = $container->get(TestNamedOutboundEvent::class);

        $this->assertEquals(new PublisherHandler($bound), $actual);
    }

    #[Test]
    public function it_throws_when_the_default_handler_is_already_set_via_the_constructor(): void
    {
        $container = new PublisherHandlerContainer(default: fn () => new class () extends TestPublisher {});

        $this->expectException(InfrastructureException::class);
        $this->expectExceptionMessage('Default event publisher handler is already set.');

        $container->withDefault(fn () => new class () extends TestPublisher {});
    }

    #[Test]
    public function it_throws_when_the_default_handler_is_already_set_via_with_default(): void
    {
        $container = new PublisherHandlerContainer();
        $container->withDefault(fn () => new class () extends TestPublisher {});

        $this->expectException(InfrastructureException::class);
        $this->expectExceptionMessage('Default event publisher handler is already set.');

        $container->withDefault(fn () => new class () extends TestPublisher {});
    }

    #[Test]
    public function it_throws_when_setting_a_string_default_handler_without_a_psr_container(): void
    {
        $container = new PublisherHandlerContainer();

        $this->expectException(InfrastructureException::class);
        $this->expectExceptionMessage(
            'Cannot bind default event publisher handler as a string without a PSR container.',
        );

        $container->withDefault(TestPublisher::class);
    }
}
