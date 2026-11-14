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

use Closure;
use CloudCreativity\Modules\Toolkit\Pipeline\MiddlewareProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MiddlewareProcessorTest extends TestCase
{
    #[Test]
    public function it_processes_middleware_in_order(): void
    {
        $processor = new MiddlewareProcessor();

        $result = $processor->process(
            [],
            $this->createMiddleware('A'),
            $this->createMiddleware('B'),
            $this->createMiddleware('C'),
        );

        $this->assertSame(['A1', 'B1', 'C1', 'C2', 'B2', 'A2'], $result);
    }

    #[Test]
    public function it_processes_middleware_with_a_destination(): void
    {
        $processor = new MiddlewareProcessor(
            static fn (array $values): array => array_map(
                static fn (string $v): string => strtolower($v), /** @phpstan-ignore-line */
                $values,
            ),
        );

        $result = $processor->process(
            [],
            $this->createMiddleware('A'),
            $this->createMiddleware('B'),
            $this->createMiddleware('C'),
        );

        $this->assertSame(['a1', 'b1', 'c1', 'C2', 'B2', 'A2'], $result);
    }

    #[Test]
    public function it_returns_the_payload_when_there_are_no_stages(): void
    {
        $processor = new MiddlewareProcessor();

        $result = $processor->process('foo');

        $this->assertSame('foo', $result);
    }

    #[Test]
    public function it_passes_the_payload_to_the_destination_when_there_are_no_stages(): void
    {
        $processor = new MiddlewareProcessor(
            static fn (string $value): string => strtoupper($value),
        );

        $result = $processor->process('foo');

        $this->assertSame('FOO', $result);
    }

    private function createMiddleware(string $step): Closure
    {
        return static function (array $values, Closure $next) use ($step) {
            $values[] = "{$step}1";
            $result = $next($values);
            assert(is_array($result));
            $result[] = "{$step}2";
            return $result;
        };
    }
}
