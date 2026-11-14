<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Toolkit\Pipeline;

use CloudCreativity\Modules\Toolkit\Pipeline\PipeContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PipeContainerTest extends TestCase
{
    #[Test]
    public function it_resolves_a_bound_pipe(): void
    {
        $a = fn () => 1;
        $b = fn () => 2;
        $container = new PipeContainer();
        $container->bind('PipeA', fn () => $a);
        $container->bind('PipeB', fn () => $b);

        $actual = $container->get('PipeB');

        $this->assertSame($b, $actual);
    }

    #[Test]
    public function it_replaces_a_pipe_that_is_bound_again(): void
    {
        $a = fn () => 1;
        $b = fn () => 2;
        $container = new PipeContainer();
        $container->bind('PipeA', fn () => $a);
        $container->bind('PipeA', fn () => $b);

        $actual = $container->get('PipeA');

        $this->assertSame($b, $actual);
    }

    #[Test]
    public function it_resolves_the_pipe_from_its_factory_each_time(): void
    {
        $container = new PipeContainer();
        $container->bind('PipeA', fn () => new class () {
            public function __invoke(): int
            {
                return 1;
            }
        });

        $first = $container->get('PipeA');
        $second = $container->get('PipeA');

        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function it_throws_when_the_pipe_is_not_bound(): void
    {
        $container = new PipeContainer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unrecognised pipe name: PipeA');

        $container->get('PipeA');
    }

    #[Test]
    public function it_throws_when_the_pipe_is_not_bound_but_others_are(): void
    {
        $container = new PipeContainer();
        $container->bind('PipeA', fn () => fn () => 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unrecognised pipe name: PipeC');

        $container->get('PipeC');
    }
}
