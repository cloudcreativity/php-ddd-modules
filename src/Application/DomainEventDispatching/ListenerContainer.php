<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Application\DomainEventDispatching;

use Closure;
use CloudCreativity\Modules\Application\ApplicationException;
use CloudCreativity\Modules\Contracts\Application\DomainEventDispatching\ListenerContainer as IListenerContainer;
use Psr\Container\ContainerInterface;

final class ListenerContainer implements IListenerContainer
{
    /**
     * @var array<non-empty-string, Closure|string>
     */
    private array $bindings = [];

    public function __construct(private readonly ?ContainerInterface $container = null)
    {
    }

    /**
     * Bind a listener factory into the container.
     *
     * @param non-empty-string $listenerName
     * @param (Closure():object)|non-empty-string $binding
     */
    public function bind(string $listenerName, Closure|string $binding): void
    {
        if (is_string($binding) && $this->container === null) {
            throw new ApplicationException('Cannot use a string listener binding without a PSR container.');
        }

        $this->bindings[$listenerName] = $binding;
    }

    public function get(string $listenerName): object
    {
        $binding = $this->bindings[$listenerName] ?? null;

        if ($binding instanceof Closure) {
            $listener = $binding();
            assert(is_object($listener), "Listener binding for {$listenerName} must return an object.");
            return $listener;
        }

        if ($this->container) {
            $target = $binding ?? $listenerName;
            $listener = $this->container->get($target);
            assert(is_object($listener), "PSR container listener binding {$target} is not an object.");
            return $listener;
        }

        throw new ApplicationException('Unrecognised listener name: ' . $listenerName);
    }
}
