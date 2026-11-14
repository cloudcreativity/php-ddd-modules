<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Application\Bus\Middleware;

use CloudCreativity\Modules\Bus\Middleware\ValidateMessage;
use CloudCreativity\Modules\Bus\Validation\WithRules;

#[WithRules(['Rule1', 'Rule2'])]
final class TestValidateMessageWithRules extends ValidateMessage
{
}
