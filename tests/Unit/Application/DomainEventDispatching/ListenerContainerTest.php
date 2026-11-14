<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\DomainEventDispatching;

use CloudCreativity\Modules\Application\DomainEventDispatching\ListenerContainer;
use CloudCreativity\Modules\Application\ApplicationException;
use CloudCreativity\Modules\Testing\FakeContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListenerContainerTest extends TestCase
{
    #[Test]
    public function it_creates_listener(): void
    {
        $listener = new class () extends TestListener {};
        $container = new ListenerContainer();
        $container->bind('bar', fn () => new class () extends TestListener {});
        $container->bind('foo', fn () => $listener);

        $actual = $container->get('foo');

        $this->assertSame($listener, $actual);
    }

    #[Test]
    public function it_does_not_recognise_listener_name(): void
    {
        $container = new ListenerContainer();

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Unrecognised listener name: foo');

        $container->get('foo');
    }

    #[Test]
    public function it_throws_when_binding_a_string_without_a_psr_container(): void
    {
        $container = new ListenerContainer();

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Cannot use a string listener binding without a PSR container.');

        $container->bind('foo', 'bar');
    }

    #[Test]
    public function it_resolves_a_string_binding_via_the_psr_container(): void
    {
        $listener = new class () extends TestListener {};
        $psrContainer = new FakeContainer();
        $psrContainer->instance('bar', $listener);
        $container = new ListenerContainer($psrContainer);
        $container->bind('foo', 'bar');

        $actual = $container->get('foo');

        $this->assertSame($listener, $actual);
    }

    #[Test]
    public function it_resolves_an_unbound_listener_name_via_the_psr_container(): void
    {
        $listener = new class () extends TestListener {};
        $psrContainer = new FakeContainer();
        $psrContainer->instance('foo', $listener);
        $container = new ListenerContainer($psrContainer);

        $actual = $container->get('foo');

        $this->assertSame($listener, $actual);
    }

    #[Test]
    public function it_prefers_a_closure_binding_over_the_psr_container(): void
    {
        $listener = new class () extends TestListener {};
        $psrContainer = new FakeContainer();
        $psrContainer->instance('foo', new class () extends TestListener {});
        $container = new ListenerContainer($psrContainer);
        $container->bind('foo', fn () => $listener);

        $actual = $container->get('foo');

        $this->assertSame($listener, $actual);
    }
}
