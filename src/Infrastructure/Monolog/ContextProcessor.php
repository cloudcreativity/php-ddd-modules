<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Infrastructure\Monolog;

use CloudCreativity\Modules\Bus\Logging\SanitizedMessage;
use CloudCreativity\Modules\Bus\Logging\SanitizedResult;
use CloudCreativity\Modules\Contracts\Toolkit\Contextual;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final readonly class ContextProcessor implements ProcessorInterface
{
    public function __construct(private ?ValueParser $parser = null)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;

        foreach ($context as $key => $value) {
            $context[$key] = match (true) {
                $value instanceof SanitizedMessage => $this->message($value),
                $value instanceof SanitizedResult => $this->result($value),
                default => $this->process($value),
            };
        }

        return $record->with(context: $context);
    }

    private function process(mixed $value): mixed
    {
        if ($value instanceof Contextual) {
            $value = $value->context();
        }

        return $this->parser ? $this->parser->parse($value) : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function message(SanitizedMessage $message): array
    {
        $values = [];

        foreach ($message as $key => $value) {
            $values[$key] = $this->process($value);
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function result(SanitizedResult $result): array
    {
        $errors = $result->errors();

        return array_filter([
            'success' => $result->success(),
            'value' => $this->process($result->value()),
            'error' => count($errors) === 1 ? $errors[0] : null,
            'errors' => count($errors) > 1 ? $errors : null,
            'meta' => $this->process($result->meta()),
        ], fn (mixed $value): bool => $value !== null);
    }
}
