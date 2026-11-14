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

use CloudCreativity\Modules\Toolkit\Pipeline\Through;
use CloudCreativity\Modules\Toolkit\Result\Result;

#[Through('Middleware1', 'Middleware2')]
final class TestCommandHandlerWithThrough
{
    /**
     * @return Result<null>
     */
    public function execute(TestCommand $command): Result
    {
        return Result::ok();
    }
}
