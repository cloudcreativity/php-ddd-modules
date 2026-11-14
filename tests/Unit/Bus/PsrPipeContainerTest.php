<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Bus;

use CloudCreativity\Modules\Bus\BusException;
use CloudCreativity\Modules\Bus\PsrPipeContainer;
use CloudCreativity\Modules\Testing\FakeContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PsrPipeContainerTest extends TestCase
{
    #[Test]
    public function it_resolves_bound_pipes(): void
    {
        $a = fn () => 1;
        $b = fn () => 2;

        $container = new PsrPipeContainer();
        $container->bind('PipeA', fn () => $a);
        $container->bind('PipeB', fn () => $b);

        $this->assertSame($a, $container->get('PipeA'));
        $this->assertSame($b, $container->get('PipeB'));
    }

    #[Test]
    public function it_fails_to_resolve_an_unbound_pipe(): void
    {
        $container = new PsrPipeContainer();
        $container->bind('PipeA', fn () => fn () => 1);

        $this->expectException(BusException::class);
        $this->expectExceptionMessage('Unrecognised pipe name: PipeC');

        $container->get('PipeC');
    }

    #[Test]
    public function it_falls_back_to_psr_container(): void
    {
        $a = fn () => 1;
        $c = fn () => 3;

        $psrContainer = new FakeContainer();
        $psrContainer->bind('PipeC', fn () => $c);

        $container = new PsrPipeContainer($psrContainer);
        $container->bind('PipeA', fn () => $a);

        $this->assertSame($a, $container->get('PipeA'));
        $this->assertSame($c, $container->get('PipeC'));
    }
}
