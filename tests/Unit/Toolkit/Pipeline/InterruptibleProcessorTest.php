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

use CloudCreativity\Modules\Toolkit\Pipeline\InterruptibleProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InterruptibleProcessorTest extends TestCase
{
    #[Test]
    public function it_interrupts_processing_when_the_checker_returns_true(): void
    {
        $processor = new InterruptibleProcessor(
            static fn (float|int $value): bool => 0 === (intval($value) % 10),
        );

        $a = static fn (int $value): int => $value * 10;
        $b = static fn (int $value): int => $value * 20;
        $c = static fn (int $value): float => $value / 3;
        $d = function () {
            $this->fail('Processor did not interrupt execution.');
        };

        $result = $processor->process(1, $a, $b, $c, $d);

        $this->assertSame(200 / 3, $result);
    }

    #[Test]
    public function it_returns_the_payload_when_there_are_no_stages(): void
    {
        $processor = new InterruptibleProcessor(function () {
            $this->fail('Not expecting checker function to be called');
        });

        $result = $processor->process(10);

        $this->assertSame(10, $result);
    }
}
