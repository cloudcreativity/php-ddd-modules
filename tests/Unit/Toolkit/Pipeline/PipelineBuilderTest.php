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

use CloudCreativity\Modules\Contracts\Toolkit\Pipeline\PipeContainer;
use CloudCreativity\Modules\Contracts\Toolkit\Pipeline\PipelineBuilder as IPipelineBuilder;
use CloudCreativity\Modules\Contracts\Toolkit\Pipeline\Processor;
use CloudCreativity\Modules\Toolkit\Pipeline\LazyPipe;
use CloudCreativity\Modules\Toolkit\Pipeline\Pipeline;
use CloudCreativity\Modules\Toolkit\Pipeline\PipelineBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PipelineBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_a_pipeline(): void
    {
        $expected = new Pipeline(
            $processor = $this->createStub(Processor::class),
            $stages = ['strtoupper', 'strtolower'],
        );

        $actual = new PipelineBuilder()
            ->add($stages[0])
            ->add($stages[1])
            ->build($processor);

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function it_builds_a_pipeline_with_lazy_pipes_for_service_strings(): void
    {
        $container = $this->createStub(PipeContainer::class);
        $processor = $this->createStub(Processor::class);

        $expected = new Pipeline($processor, [
            'strtoupper',
            new LazyPipe($container, 'SomeService'),
            'strtolower',
        ]);

        $actual = new PipelineBuilder($container)
            ->through(['strtoupper', 'SomeService', 'strtolower'])
            ->build($processor);

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function it_fails_to_build_with_a_service_string_without_a_container(): void
    {
        $processor = $this->createStub(Processor::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot use a string pipe name without a pipe container.');

        $_ = new PipelineBuilder()
            ->add('strtoupper')
            ->add('SomeService')
            ->add('strtolower')
            ->build($processor);
    }

    #[Test]
    public function it_makes_a_builder(): void
    {
        $container = $this->createStub(PipeContainer::class);

        $actual = PipelineBuilder::make($container);

        $this->assertInstanceOf(IPipelineBuilder::class, $actual);
        $this->assertEquals(new PipelineBuilder($container), $actual);
    }

    #[Test]
    public function it_makes_a_builder_without_a_container(): void
    {
        $actual = PipelineBuilder::make();

        $this->assertEquals(new PipelineBuilder(), $actual);
    }

    #[Test]
    public function it_builds_a_pipeline_with_the_default_processor(): void
    {
        $pipeline = new PipelineBuilder()
            ->through([
                static fn (int $value): int => $value * 5,
                static fn (int $value): int => $value + 2,
            ])
            ->build();

        $actual = $pipeline->process(3);

        $this->assertSame(17, $actual);
    }

    #[Test]
    public function it_builds_an_empty_pipeline(): void
    {
        $processor = $this->createStub(Processor::class);

        $actual = new PipelineBuilder()->build($processor);

        $this->assertEquals(new Pipeline($processor, []), $actual);
    }
}
