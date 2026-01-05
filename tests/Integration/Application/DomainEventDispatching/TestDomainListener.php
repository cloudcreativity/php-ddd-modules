<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Integration\Application\DomainEventDispatching;

use CloudCreativity\Modules\Contracts\Domain\Events\DomainEvent;

final class TestDomainListener
{
    /**
     * @var array<DomainEvent>
     */
    public array $events = [];

    public function handle(DomainEvent $event): void
    {
        $this->events[] = $event;
    }
}
