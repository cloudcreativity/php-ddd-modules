<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\Bus;

use CloudCreativity\Modules\Bus\BusException;
use CloudCreativity\Modules\Contracts\Bus\BusException as IBusException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BusExceptionTest extends TestCase
{
    #[Test]
    public function it_is_a_runtime_bus_exception(): void
    {
        $ex = new BusException('Something went wrong.');

        $this->assertInstanceOf(RuntimeException::class, $ex);
        $this->assertInstanceOf(IBusException::class, $ex);
        $this->assertSame('Something went wrong.', $ex->getMessage());
    }
}
