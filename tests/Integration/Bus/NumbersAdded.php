<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Integration\Bus;

use CloudCreativity\Modules\Contracts\Messages\IntegrationEvent;
use CloudCreativity\Modules\Toolkit\Contracts;
use CloudCreativity\Modules\Toolkit\Identifiers\Uuid;
use DateTimeImmutable;

final readonly class NumbersAdded implements IntegrationEvent
{
    public Uuid $uuid;

    public function __construct(
        public int $a,
        public int $b,
        public int $sum,
        public DateTimeImmutable $calculatedAt = new DateTimeImmutable(),
    ) {
        Contracts::assert($sum === ($a + $b), 'The sum must be equal to a plus b.');
        $this->uuid = Uuid::random();
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->calculatedAt;
    }
}
