<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Bus\Validation;

use CloudCreativity\Modules\Contracts\Messages\Command;
use CloudCreativity\Modules\Contracts\Messages\Query;
use CloudCreativity\Modules\Contracts\Toolkit\Pipeline\Processor;
use CloudCreativity\Modules\Toolkit\Result\ListOfErrors;

final readonly class ValidationProcessor implements Processor
{
    public function __construct(private bool $stopOnFirstFailure = false)
    {
    }

    /**
     * @param (callable(Command|Query): ?ListOfErrors) ...$stages
     */
    public function process(mixed $payload, callable ...$stages): ListOfErrors
    {
        assert($payload instanceof Command || $payload instanceof Query);

        $errors = new ListOfErrors();

        foreach ($stages as $stage) {
            $result = $stage($payload);

            if ($result) {
                $errors = $errors->merge($result);
            }

            if ($this->stopOnFirstFailure && $errors->isNotEmpty()) {
                return $errors;
            }
        }

        return $errors;
    }
}
