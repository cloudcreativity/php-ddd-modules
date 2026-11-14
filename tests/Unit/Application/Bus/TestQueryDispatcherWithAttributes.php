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

use CloudCreativity\Modules\Bus\QueryDispatcher;
use CloudCreativity\Modules\Bus\WithQuery;
use CloudCreativity\Modules\Toolkit\Pipeline\Through;

#[Through('Middleware1')]
#[WithQuery(TestQuery::class, TestQueryHandler::class)]
final class TestQueryDispatcherWithAttributes extends QueryDispatcher
{
}
