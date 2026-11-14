<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Testing;

use CloudCreativity\Modules\Contracts\Application\Ports\ExceptionReporter;
use CloudCreativity\Modules\Contracts\Application\Ports\UnitOfWork;
use CloudCreativity\Modules\Testing\FakeContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use stdClass;

final class FakeContainerTest extends TestCase
{
    private FakeContainer $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new FakeContainer();
    }

    protected function tearDown(): void
    {
        unset($this->container);
        parent::tearDown();
    }

    #[Test]
    public function it_is_a_psr_container(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, $this->container);
    }

    #[Test]
    public function it_shares_the_exception_reporter_with_the_unit_of_work(): void
    {
        $actual = $this->container->unitOfWork->exceptions;

        $this->assertSame($this->container->reporter, $actual);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_resolves_the_exception_reporter(): void
    {
        $actual = $this->container->get(ExceptionReporter::class);

        $this->assertSame($this->container->reporter, $actual);
    }

    #[Test]
    public function it_resolves_the_logger(): void
    {
        $actual = $this->container->get(LoggerInterface::class);

        $this->assertSame($this->container->logger, $actual);
    }

    #[Test]
    public function it_resolves_the_unit_of_work(): void
    {
        $actual = $this->container->get(UnitOfWork::class);

        $this->assertSame($this->container->unitOfWork, $actual);
    }

    /**
     * @param class-string $id
     */
    #[DataProvider('builtInServiceProvider')]
    #[Test]
    public function it_resolves_a_binding_that_overrides_a_built_in_service(string $id): void
    {
        $instance = new stdClass();
        $this->container->instance($id, $instance);

        $actual = $this->container->get($id);

        $this->assertSame($instance, $actual);
    }

    #[Test]
    public function it_resolves_a_binding_each_time_it_is_requested(): void
    {
        $this->container->bind('foo', fn (): stdClass => new stdClass());

        $first = $this->container->get('foo');
        $second = $this->container->get('foo');

        $this->assertInstanceOf(stdClass::class, $first);
        $this->assertInstanceOf(stdClass::class, $second);
        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function it_replaces_an_existing_binding(): void
    {
        $this->container->bind('foo', fn (): string => 'bar');
        $this->container->bind('foo', fn (): string => 'baz');

        $actual = $this->container->get('foo');

        $this->assertSame('baz', $actual);
    }

    #[Test]
    public function it_resolves_an_instance(): void
    {
        $instance = new stdClass();
        $this->container->instance('foo', $instance);

        $actual = $this->container->get('foo');

        $this->assertSame($instance, $actual);
    }

    #[Test]
    public function it_resolves_a_falsy_instance(): void
    {
        $this->container->instance('foo', false);

        $actual = $this->container->get('foo');

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_throws_when_the_service_is_not_found(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);
        $this->expectExceptionMessage('Test service not found: foo');

        $this->container->get('foo');
    }

    #[Test]
    public function it_throws_when_the_binding_resolves_to_null(): void
    {
        $this->container->instance('foo', null);

        $this->expectException(NotFoundExceptionInterface::class);
        $this->expectExceptionMessage('Test service not found: foo');

        $this->container->get('foo');
    }

    // -------------------------------------------------------------------------
    // has()
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{class-string}>
     */
    public static function builtInServiceProvider(): array
    {
        return [
            'exception reporter' => [ExceptionReporter::class],
            'logger' => [LoggerInterface::class],
            'unit of work' => [UnitOfWork::class],
        ];
    }

    /**
     * @param class-string $id
     */
    #[DataProvider('builtInServiceProvider')]
    #[Test]
    public function it_has_the_built_in_services(string $id): void
    {
        $actual = $this->container->has($id);

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_has_a_bound_service(): void
    {
        $this->container->bind('foo', fn (): string => 'bar');

        $actual = $this->container->has('foo');

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_has_an_instance(): void
    {
        $this->container->instance('foo', new stdClass());

        $actual = $this->container->has('foo');

        $this->assertTrue($actual);
    }

    #[Test]
    public function it_does_not_have_an_unknown_service(): void
    {
        $actual = $this->container->has('foo');

        $this->assertFalse($actual);
    }

    #[Test]
    public function it_does_not_resolve_a_binding_when_checking_if_it_exists(): void
    {
        $resolved = false;
        $this->container->bind('foo', function () use (&$resolved): string {
            $resolved = true;
            return 'bar';
        });

        $actual = $this->container->has('foo');

        $this->assertTrue($actual);
        $this->assertFalse($resolved);
    }
}
