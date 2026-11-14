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

use CloudCreativity\Modules\Contracts\Toolkit\Pipeline\Processor;
use CloudCreativity\Modules\Toolkit\Pipeline\Pipeline;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PipelineTest extends TestCase
{
    #[Test]
    public function it_processes_the_payload(): void
    {
        $payload = 'Hello World';
        $stages = ['strtoupper', 'strtolower'];

        $processor = $this->createMock(Processor::class);

        $processor
            ->expects($this->once())
            ->method('process')
            ->with($payload, ...$stages)
            ->willReturn($expected = 'hello world');

        $pipeline = new Pipeline($processor, $stages);

        $actual = $pipeline->process($payload);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_is_invokable(): void
    {
        $payload = 'Hello World';
        $stages = ['strtoupper', 'strtolower'];

        $processor = $this->createMock(Processor::class);

        $processor
            ->expects($this->once())
            ->method('process')
            ->with($payload, ...$stages)
            ->willReturn($expected = 'hello world');

        $pipeline = new Pipeline($processor, $stages);

        $actual = $pipeline($payload);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_uses_a_default_processor(): void
    {
        $pipeline = new Pipeline(null, [
            static fn (int $value): int => $value * 5,
            static fn (int $value): int => $value + 2,
            static fn (int $value): int => $value - 3,
        ]);

        $result = $pipeline->process(3);

        $this->assertSame(14, $result);
    }

    #[Test]
    public function it_returns_a_new_pipeline_when_piping(): void
    {
        $pipeline1 = new Pipeline(null, [
            static fn (int $value): int => $value * 5,
            static fn (int $value): int => $value + 2,
        ]);

        $pipeline2 = $pipeline1->pipe(
            static fn (int $value): int => $value - 3,
        );

        $this->assertNotSame($pipeline1, $pipeline2);
        $this->assertSame(17, $pipeline1->process(3));
        $this->assertSame(14, $pipeline2->process(3));
    }
}
