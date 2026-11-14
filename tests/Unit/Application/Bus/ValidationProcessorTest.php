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
use CloudCreativity\Modules\Bus\Validation\ValidationProcessor;
use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Contracts\Messaging\Message;
use CloudCreativity\Modules\Toolkit\Result\Error;
use CloudCreativity\Modules\Toolkit\Result\ListOfErrors;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidationProcessorTest extends TestCase
{
    #[Test]
    public function it_throws_when_the_payload_is_not_a_message(): void
    {
        $processor = new ValidationProcessor();

        $this->expectException(BusException::class);
        $this->expectExceptionMessage('Expecting a message to validate.');

        $processor->process(new \stdClass());
    }

    #[Test]
    public function it_returns_no_errors_when_there_are_no_stages(): void
    {
        $processor = new ValidationProcessor();

        $actual = $processor->process($this->createStub(Command::class));

        $this->assertEquals(new ListOfErrors(), $actual);
    }

    #[Test]
    public function it_collects_errors_from_every_stage(): void
    {
        $message = $this->createStub(Command::class);
        $error1 = new Error(null, 'Error 1');
        $error2 = new Error(null, 'Error 2');
        $error3 = new Error(null, 'Error 3');
        $received = [];
        $processor = new ValidationProcessor();

        $actual = $processor->process(
            $message,
            function (Message $actual) use (&$received, $error1): Error {
                $received[] = $actual;
                return $error1;
            },
            function (Message $actual) use (&$received): null {
                $received[] = $actual;
                return null;
            },
            function (Message $actual) use (&$received, $error2, $error3): ListOfErrors {
                $received[] = $actual;
                return new ListOfErrors($error2, $error3);
            },
        );

        $this->assertEquals(new ListOfErrors($error1, $error2, $error3), $actual);
        $this->assertSame([$message, $message, $message], $received);
    }

    #[Test]
    public function it_stops_on_first_failure(): void
    {
        $error = new Error(null, 'Error 1');
        $processor = new ValidationProcessor(stopOnFirstFailure: true);

        $actual = $processor->process(
            $this->createStub(Command::class),
            fn (): null => null,
            fn (): Error => $error,
            fn (): never => $this->fail('Not expecting stage to be called.'),
        );

        $this->assertEquals(new ListOfErrors($error), $actual);
    }

    #[Test]
    public function it_does_not_stop_when_stages_pass(): void
    {
        $error = new Error(null, 'Error 1');
        $processor = new ValidationProcessor(stopOnFirstFailure: true);

        $actual = $processor->process(
            $this->createStub(Command::class),
            fn (): null => null,
            fn (): ListOfErrors => new ListOfErrors(),
            fn (): Error => $error,
        );

        $this->assertEquals(new ListOfErrors($error), $actual);
    }
}
