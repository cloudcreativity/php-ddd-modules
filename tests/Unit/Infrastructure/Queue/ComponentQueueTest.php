<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Infrastructure\Queue;

use CloudCreativity\Modules\Contracts\Infrastructure\Queue\Enqueuer;
use CloudCreativity\Modules\Contracts\Infrastructure\Queue\EnqueuerContainer as IEnqueuerContainer;
use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Contracts\Toolkit\Pipeline\PipeContainer as IPipeContainer;
use CloudCreativity\Modules\Infrastructure\Queue\ComponentQueue;
use CloudCreativity\Modules\Infrastructure\Queue\EnqueuerContainer;
use CloudCreativity\Modules\Testing\FakeContainer;
use CloudCreativity\Modules\Tests\Unit\Application\Bus\TestCommand;
use CloudCreativity\Modules\Toolkit\Pipeline\PipeContainer;
use Closure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ComponentQueueTest extends TestCase
{
    /**
     * @var array<string>
     */
    private array $sequence = [];

    protected function tearDown(): void
    {
        unset($this->sequence);
        parent::tearDown();
    }

    #[Test]
    public function it_queues_a_command(): void
    {
        $command = new TestQueueableCommand();
        $enqueuer = $this->createRecordingEnqueuer();
        $enqueuers = new EnqueuerContainer();
        $enqueuers->bind(TestQueueableCommand::class, fn () => $enqueuer);
        $queue = new class (
            enqueuers: $enqueuers,
            middleware: new PipeContainer(),
        ) extends ComponentQueue {};

        $queue->push($command);

        $this->assertSame([$command], $enqueuer->pushed);
    }

    #[Test]
    public function it_queues_through_middleware(): void
    {
        $command1 = new TestQueueableCommand('one');
        $command2 = new TestQueueableCommand('two');
        $command3 = new TestQueueableCommand('three');
        $enqueuer = $this->createRecordingEnqueuer();
        $enqueuers = new EnqueuerContainer(default: fn () => $enqueuer);

        $middleware1 = function (Command $actual, Closure $next) use ($command1, $command2): void {
            $this->assertSame($command1, $actual);
            $this->sequence[] = 'before1';
            $next($command2);
            $this->sequence[] = 'after1';
        };

        $middleware2 = function (Command $actual, Closure $next) use ($command2, $command3): void {
            $this->assertSame($command2, $actual);
            $this->sequence[] = 'before2';
            $next($command3);
            $this->sequence[] = 'after2';
        };

        $middleware = new PipeContainer();
        $middleware->bind('MySecondMiddleware', fn () => $middleware2);

        $queue = new class (
            enqueuers: $enqueuers,
            middleware: $middleware,
        ) extends ComponentQueue {};
        $queue->through([$middleware1, 'MySecondMiddleware']);

        $queue->push($command1);

        $this->assertSame([$command3], $enqueuer->pushed);
        $this->assertSame(['before1', 'before2', 'pushed', 'after2', 'after1'], $this->sequence);
    }

    #[Test]
    public function it_queues_via_an_enqueuer_bound_with_an_attribute(): void
    {
        $command = new TestQueueableCommand();
        $queue = new TestComponentQueueWithAttributes($this->createContainer(
            $bound = $this->createRecordingEnqueuer(),
            $default = $this->createRecordingEnqueuer(),
        ));

        $queue->push($command);

        $this->assertSame([$command], $bound->pushed);
        $this->assertSame([], $default->pushed);
    }

    #[Test]
    public function it_queues_via_an_enqueuer_bound_to_an_array_of_commands_with_an_attribute(): void
    {
        $command = new TestCommand();
        $queue = new TestComponentQueueWithAttributes($this->createContainer(
            $bound = $this->createRecordingEnqueuer(),
            $default = $this->createRecordingEnqueuer(),
        ));

        $queue->push($command);

        $this->assertSame([$command], $bound->pushed);
        $this->assertSame([], $default->pushed);
    }

    #[Test]
    public function it_queues_via_the_default_enqueuer_bound_with_an_attribute(): void
    {
        $command = $this->createStub(Command::class);
        $queue = new TestComponentQueueWithAttributes($this->createContainer(
            $bound = $this->createRecordingEnqueuer(),
            $default = $this->createRecordingEnqueuer(),
        ));

        $queue->push($command);

        $this->assertSame([], $bound->pushed);
        $this->assertSame([$command], $default->pushed);
    }

    #[Test]
    public function it_queues_through_middleware_from_the_through_attribute(): void
    {
        $queue = new TestComponentQueueWithAttributes($this->createContainer(
            $this->createRecordingEnqueuer(),
            $this->createRecordingEnqueuer(),
        ));

        $queue->push(new TestQueueableCommand());

        $this->assertSame(['before1', 'before2', 'pushed', 'after2', 'after1'], $this->sequence);
    }

    #[Test]
    public function it_does_not_autowire_enqueuer_attributes_when_given_an_enqueuer_container(): void
    {
        $command = new TestQueueableCommand();
        $enqueuers = $this->createMock(IEnqueuerContainer::class);
        $middleware = $this->createMock(IPipeContainer::class);
        $queue = new TestComponentQueueWithAttributes($enqueuers, $middleware);

        $middleware
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(fn (string $name): Closure => match ($name) {
                'Middleware1', 'Middleware2' => fn (Command $c, Closure $next) => $next($c),
                default => $this->fail('Unexpected middleware: ' . $name),
            });

        $enqueuers
            ->expects($this->once())
            ->method('get')
            ->with($command::class)
            ->willReturn($enqueuer = $this->createMock(Enqueuer::class));

        $enqueuer
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($command));

        $queue->push($command);
    }

    private function createContainer(TestEnqueuer $bound, TestEnqueuer $default): FakeContainer
    {
        $container = new FakeContainer();
        $container->instance(TestEnqueuer::class, $bound);
        $container->instance('DefaultEnqueuer', $default);
        $container->instance('Middleware1', function (Command $command, Closure $next): void {
            $this->sequence[] = 'before1';
            $next($command);
            $this->sequence[] = 'after1';
        });
        $container->instance('Middleware2', function (Command $command, Closure $next): void {
            $this->sequence[] = 'before2';
            $next($command);
            $this->sequence[] = 'after2';
        });

        return $container;
    }

    /**
     * @return object{pushed: list<Command>}&TestEnqueuer
     */
    private function createRecordingEnqueuer(): TestEnqueuer
    {
        return new class (function (): void {
            $this->sequence[] = 'pushed';
        }) extends TestEnqueuer {
            /**
             * @var list<Command>
             */
            public array $pushed = [];

            public function __construct(private readonly Closure $onPush)
            {
            }

            public function push(Command $command): void
            {
                $this->pushed[] = $command;
                ($this->onPush)();
            }
        };
    }
}
