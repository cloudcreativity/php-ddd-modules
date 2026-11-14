<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Infrastructure;

use CloudCreativity\Modules\Infrastructure\InfrastructureException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InfrastructureExceptionTest extends TestCase
{
    #[Test]
    public function it_is_a_runtime_exception(): void
    {
        $ex = new InfrastructureException();
        $this->assertInstanceOf(RuntimeException::class, $ex);
    }
}
