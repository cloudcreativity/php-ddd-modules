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

use Attribute;
use CloudCreativity\Modules\Toolkit\Pipeline\Through;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ThroughTest extends TestCase
{
    #[Test]
    public function it_holds_the_pipes(): void
    {
        $through = new Through('PipeA', 'PipeB', 'PipeC');

        $this->assertSame(['PipeA', 'PipeB', 'PipeC'], $through->pipes);
    }

    #[Test]
    public function it_can_have_no_pipes(): void
    {
        $through = new Through();

        $this->assertSame([], $through->pipes);
    }

    #[Test]
    public function it_lists_pipes_passed_with_keys(): void
    {
        $pipes = ['a' => 'PipeA', 'b' => 'PipeB'];

        $through = new Through(...$pipes);

        $this->assertSame(['PipeA', 'PipeB'], $through->pipes);
    }

    #[Test]
    public function it_is_read_from_a_class(): void
    {
        $object = new #[Through('PipeA', 'PipeB')] class () {
        };
        $reflection = new ReflectionClass($object);

        $through = $reflection->getAttributes(Through::class)[0]->newInstance();

        $this->assertSame(['PipeA', 'PipeB'], $through->pipes);
    }

    #[Test]
    public function it_only_targets_classes(): void
    {
        $reflection = new ReflectionClass(Through::class);

        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        $this->assertSame(Attribute::TARGET_CLASS, $attribute->flags);
    }
}
