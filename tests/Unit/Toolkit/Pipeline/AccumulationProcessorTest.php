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

use CloudCreativity\Modules\Toolkit\Pipeline\AccumulationProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AccumulationProcessorTest extends TestCase
{
    #[Test]
    public function it_accumulates_stage_results(): void
    {
        $input = new \DateTime();

        $processor = new AccumulationProcessor(
            static fn (?int $carry, int $result) => intval($carry) + $result,
        );

        $a = function ($actual) use ($input): int {
            $this->assertSame($actual, $input);
            return 1;
        };

        $b = function ($actual) use ($input): int {
            $this->assertSame($actual, $input);
            return 2;
        };

        $c = function ($actual) use ($input): int {
            $this->assertSame($actual, $input);
            return 3;
        };

        $result = $processor->process($input, $a, $b, $c);

        $this->assertSame(6, $result);
    }

    #[Test]
    public function it_accumulates_stage_results_with_an_initial_value(): void
    {
        $input = new \DateTime();

        $processor = new AccumulationProcessor(
            static fn (int $carry, int $result) => $carry + $result,
            10,
        );

        $a = function ($actual) use ($input): int {
            $this->assertSame($actual, $input);
            return 1;
        };

        $b = function ($actual) use ($input): int {
            $this->assertSame($actual, $input);
            return 2;
        };

        $c = function ($actual) use ($input): int {
            $this->assertSame($actual, $input);
            return 3;
        };

        $result = $processor->process($input, $a, $b, $c);

        $this->assertSame(16, $result);
    }

    #[Test]
    public function it_returns_null_when_there_are_no_stages_or_initial_value(): void
    {
        $processor = new AccumulationProcessor(function () {
            $this->fail('Callback should not be executed on an empty stack.');
        });

        $this->assertNull($processor->process('foobar'));
    }

    #[Test]
    public function it_returns_the_initial_value_when_there_are_no_stages(): void
    {
        $processor = new AccumulationProcessor(function () {
            $this->fail('Callback should not be executed on an empty stack.');
        }, 10);

        $this->assertSame(10, $processor->process('foobar'));
    }
}
