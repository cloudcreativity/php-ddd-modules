<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Testing;

use Closure;
use CloudCreativity\Modules\Contracts\Application\Ports\ExceptionReporter;
use CloudCreativity\Modules\Contracts\Application\Ports\UnitOfWork;
use Exception;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

final class FakeContainer implements ContainerInterface
{
    public readonly FakeLogger $logger;
    public readonly FakeExceptionReporter $reporter;
    public readonly FakeUnitOfWork $unitOfWork;

    /**
     * @var array<string, Closure>
     */
    private array $bindings = [];

    public function __construct()
    {
        $this->logger = new FakeLogger();
        $this->reporter = new FakeExceptionReporter();
        $this->unitOfWork = new FakeUnitOfWork($this->reporter);
    }

    public function bind(string $id, Closure $binding): void
    {
        $this->bindings[$id] = $binding;
    }

    public function instance(string $id, mixed $instance): void
    {
        $this->bindings[$id] = fn () => $instance;
    }

    public function get(string $id)
    {
        if (isset($this->bindings[$id])) {
            return $this->bindings[$id]() ?? $this->abort($id);
        }

        return match ($id) {
            ExceptionReporter::class => $this->reporter,
            LoggerInterface::class => $this->logger,
            UnitOfWork::class => $this->unitOfWork,
            default => $this->abort($id),
        };
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || in_array($id, [
            ExceptionReporter::class,
            LoggerInterface::class,
            UnitOfWork::class,
        ], true);
    }

    protected function abort(string $id): never
    {
        $message = 'Test service not found: ' . $id;

        throw new class ($message) extends Exception implements NotFoundExceptionInterface {};
    }
}
