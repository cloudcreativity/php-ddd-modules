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
use CloudCreativity\Modules\Contracts\Toolkit\Pipeline\Pipeline;
use CloudCreativity\Modules\Toolkit\Pipeline\LazyPipe;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LazyPipeTest extends TestCase
{
    /**
     * @var MockObject&PipeContainer
     */
    private PipeContainer $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $this->createMock(PipeContainer::class);
    }

    protected function tearDown(): void
    {
        unset($this->container);
        parent::tearDown();
    }

    #[Test]
    public function it_resolves_the_pipe_from_the_container_and_invokes_it(): void
    {
        $pipe = $this->createMock(Pipeline::class);
        $lazyPipe = new LazyPipe($this->container, 'SomePipe');

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('SomePipe')
            ->willReturn($pipe);

        $pipe
            ->expects($this->once())
            ->method('__invoke')
            ->with('arg1', 'arg2')
            ->willReturn($expected = 'Hello World!');

        $actual = $lazyPipe('arg1', 'arg2');

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_throws_when_the_pipe_cannot_be_resolved(): void
    {
        $lazyPipe = new LazyPipe($this->container, 'PipeThatWillError');

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('PipeThatWillError')
            ->willThrowException(new LogicException('Boom!'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get pipe "PipeThatWillError" from container.');
        $this->expectExceptionCode(0);

        $lazyPipe('blah');
    }

    #[Test]
    public function it_sets_the_container_exception_as_the_previous_exception(): void
    {
        $lazyPipe = new LazyPipe($this->container, 'PipeThatWillError');

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('PipeThatWillError')
            ->willThrowException($expected = new LogicException('Boom!'));

        $actual = null;

        try {
            $lazyPipe('blah');
        } catch (RuntimeException $ex) {
            $actual = $ex->getPrevious();
        }

        $this->assertSame($expected, $actual);
    }
}
