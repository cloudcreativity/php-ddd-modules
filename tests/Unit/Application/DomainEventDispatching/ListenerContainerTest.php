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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListenerContainerTest extends TestCase
{
    #[Test]
    public function it_creates_listener(): void
    {
        $container = new ListenerContainer();
        $listener = new class () extends TestListener {};

        $container->bind('bar', fn () => new class () extends TestListener {});
        $container->bind('foo', fn () => $listener);

        $this->assertSame($listener, $container->get('foo'));
    }

    #[Test]
    public function it_does_not_recognise_listener_name(): void
    {
        $container = new ListenerContainer();
        $this->expectExceptionMessage('Unrecognised listener name: foo');
        $container->get('foo');
    }

    #[Test]
    public function it_handles_binding_not_returning_an_object(): void
    {
        $container = new ListenerContainer();
        /** @phpstan-ignore-next-line */
        $container->bind('foo', fn () => 'bar');
        $this->expectExceptionMessage('Listener binding for foo must return an object.');
        $container->get('foo');
    }
}
