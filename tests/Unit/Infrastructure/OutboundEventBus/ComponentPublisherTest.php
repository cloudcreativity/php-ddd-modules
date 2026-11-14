<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Infrastructure\OutboundEventBus;

use Closure;
use CloudCreativity\Modules\Contracts\Infrastructure\OutboundEventBus\PublisherHandler;
use CloudCreativity\Modules\Contracts\Infrastructure\OutboundEventBus\PublisherHandlerContainer as IPublisherHandlerContainer;
use CloudCreativity\Modules\Contracts\Messaging\IntegrationEvent;
use CloudCreativity\Modules\Contracts\Toolkit\Pipeline\PipeContainer as IPipeContainer;
use CloudCreativity\Modules\Infrastructure\OutboundEventBus\ComponentPublisher;
use CloudCreativity\Modules\Infrastructure\OutboundEventBus\PublisherHandlerContainer;
use CloudCreativity\Modules\Testing\FakeContainer;
use CloudCreativity\Modules\Toolkit\Pipeline\PipeContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ComponentPublisherTest extends TestCase
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
    public function it_publishes_an_event(): void
    {
        $event = new TestNamedOutboundEvent();
        $handler = $this->createRecordingPublisher();
        $handlers = new PublisherHandlerContainer();
        $handlers->bind(TestNamedOutboundEvent::class, fn () => $handler);
        $publisher = new class (
            handlers: $handlers,
            middleware: new PipeContainer(),
        ) extends ComponentPublisher {};

        $publisher->publish($event);

        $this->assertSame([$event], $handler->published);
    }

    #[Test]
    public function it_publishes_an_event_through_middleware(): void
    {
        $event1 = new TestNamedOutboundEvent('one');
        $event2 = new TestNamedOutboundEvent('two');
        $event3 = new TestNamedOutboundEvent('three');
        $handler = $this->createRecordingPublisher();
        $handlers = new PublisherHandlerContainer(default: fn () => $handler);

        $middleware1 = function (IntegrationEvent $actual, Closure $next) use ($event1, $event2): void {
            $this->assertSame($event1, $actual);
            $this->sequence[] = 'before1';
            $next($event2);
            $this->sequence[] = 'after1';
        };

        $middleware2 = function (IntegrationEvent $actual, Closure $next) use ($event2, $event3): void {
            $this->assertSame($event2, $actual);
            $this->sequence[] = 'before2';
            $next($event3);
            $this->sequence[] = 'after2';
        };

        $middleware = new PipeContainer();
        $middleware->bind('Middleware2', fn () => $middleware2);

        $publisher = new class (
            handlers: $handlers,
            middleware: $middleware,
        ) extends ComponentPublisher {};
        $publisher->through([$middleware1, 'Middleware2']);

        $publisher->publish($event1);

        $this->assertSame([$event3], $handler->published);
        $this->assertSame(['before1', 'before2', 'published', 'after2', 'after1'], $this->sequence);
    }

    #[Test]
    public function it_publishes_via_a_publisher_bound_with_an_attribute(): void
    {
        $event = new TestNamedOutboundEvent();
        $publisher = new TestComponentPublisherWithAttributes($this->createContainer(
            $bound = $this->createRecordingPublisher(),
            $default = $this->createRecordingPublisher(),
        ));

        $publisher->publish($event);

        $this->assertSame([$event], $bound->published);
        $this->assertSame([], $default->published);
    }

    #[Test]
    public function it_publishes_via_the_default_publisher_bound_with_an_attribute(): void
    {
        $event = new class () extends TestOutboundEvent {};
        $publisher = new TestComponentPublisherWithAttributes($this->createContainer(
            $bound = $this->createRecordingPublisher(),
            $default = $this->createRecordingPublisher(),
        ));

        $publisher->publish($event);

        $this->assertSame([], $bound->published);
        $this->assertSame([$event], $default->published);
    }

    #[Test]
    public function it_publishes_through_middleware_from_the_through_attribute(): void
    {
        $publisher = new TestComponentPublisherWithAttributes($this->createContainer(
            $this->createRecordingPublisher(),
            $this->createRecordingPublisher(),
        ));

        $publisher->publish(new TestNamedOutboundEvent());

        $this->assertSame(['before1', 'before2', 'published', 'after2', 'after1'], $this->sequence);
    }

    #[Test]
    public function it_does_not_autowire_publisher_attributes_when_given_a_handler_container(): void
    {
        $event = new TestNamedOutboundEvent();
        $handlers = $this->createMock(IPublisherHandlerContainer::class);
        $middleware = $this->createMock(IPipeContainer::class);
        $publisher = new TestComponentPublisherWithAttributes($handlers, $middleware);

        $middleware
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(fn (string $name): Closure => match ($name) {
                'Middleware1', 'Middleware2' => fn (IntegrationEvent $e, Closure $next) => $next($e),
                default => $this->fail('Unexpected middleware: ' . $name),
            });

        $handlers
            ->expects($this->once())
            ->method('get')
            ->with($event::class)
            ->willReturn($handler = $this->createMock(PublisherHandler::class));

        $handler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($event));

        $publisher->publish($event);
    }

    private function createContainer(TestPublisher $bound, TestPublisher $default): FakeContainer
    {
        $container = new FakeContainer();
        $container->instance(TestPublisher::class, $bound);
        $container->instance('DefaultPublisher', $default);
        $container->instance('Middleware1', function (IntegrationEvent $event, Closure $next): void {
            $this->sequence[] = 'before1';
            $next($event);
            $this->sequence[] = 'after1';
        });
        $container->instance('Middleware2', function (IntegrationEvent $event, Closure $next): void {
            $this->sequence[] = 'before2';
            $next($event);
            $this->sequence[] = 'after2';
        });

        return $container;
    }

    /**
     * @return object{published: list<TestOutboundEvent>}&TestPublisher
     */
    private function createRecordingPublisher(): TestPublisher
    {
        return new class (function (): void {
            $this->sequence[] = 'published';
        }) extends TestPublisher {
            /**
             * @var list<TestOutboundEvent>
             */
            public array $published = [];

            public function __construct(private readonly Closure $onPublish)
            {
            }

            public function publish(TestOutboundEvent $event): void
            {
                $this->published[] = $event;
                ($this->onPublish)();
            }
        };
    }
}
