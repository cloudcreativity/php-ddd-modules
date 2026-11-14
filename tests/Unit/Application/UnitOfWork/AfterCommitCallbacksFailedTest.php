<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\UnitOfWork;

use CloudCreativity\Modules\Application\UnitOfWork\AfterCommitCallbacksFailed;
use CloudCreativity\Modules\Contracts\Application\ApplicationException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AfterCommitCallbacksFailedTest extends TestCase
{
    #[Test]
    public function it_wraps_multiple_exceptions(): void
    {
        $exceptions = [
            $ex1 = new LogicException('Boom 1!'),
            new RuntimeException('Boom 2!'),
            new LogicException('Boom 3!'),
        ];

        $actual = new AfterCommitCallbacksFailed($exceptions);

        $this->assertInstanceOf(ApplicationException::class, $actual);
        $this->assertSame('3 after commit callbacks failed.', $actual->getMessage());
        $this->assertSame($exceptions, $actual->exceptions());
        $this->assertSame($ex1, $actual->getPrevious());
    }
}
