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

use Attribute;
use CloudCreativity\Modules\Contracts\Domain\Events\DomainEvent;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class ListenTo
{
    /**
     * @param class-string<DomainEvent> $event
     * @param list<non-empty-string>|non-empty-string $listeners
     */
    public function __construct(public string $event, public array|string $listeners)
    {
    }
}
