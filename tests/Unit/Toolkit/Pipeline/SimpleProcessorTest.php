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

use CloudCreativity\Modules\Toolkit\Pipeline\SimpleProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SimpleProcessorTest extends TestCase
{
    #[Test]
    public function it_processes_stages_in_order(): void
    {
        $processor = new SimpleProcessor();

        $a = static fn (int $value): int => $value * 2;
        $b = static fn (int $value): int => $value + 1;
        $c = static fn (int $value): int => $value * 3;

        $result = $processor->process(10, $a, $b, $c);

        $this->assertSame(63, $result);
    }

    #[Test]
    public function it_returns_the_payload_when_there_are_no_stages(): void
    {
        $processor = new SimpleProcessor();

        $result = $processor->process(10);

        $this->assertSame(10, $result);
    }
}
