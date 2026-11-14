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

use CloudCreativity\Modules\Bus\PsrPipeContainer;
use CloudCreativity\Modules\Bus\Validation\Validator;
use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Contracts\Messaging\Message;
use CloudCreativity\Modules\Contracts\Messaging\Query;
use CloudCreativity\Modules\Testing\FakeContainer;
use CloudCreativity\Modules\Toolkit\Result\Error;
use CloudCreativity\Modules\Toolkit\Result\ListOfErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    /**
     * @return list<array{class-string<Message>}>
     */
    public static function messageProvider(): array
    {
        return [
            [Query::class],
            [Command::class],
            [Message::class],
        ];
    }

    /**
     * @param class-string<Message> $class
     */
    #[DataProvider('messageProvider')]
    #[Test]
    public function it_validates_message(string $class): void
    {
        $message = $this->createStub($class);
        $error1 = new Error(null, 'Message 1');
        $error2 = new Error(null, 'Message 2');
        $error3 = new Error(null, 'Message 3');

        $a = function ($actual) use ($message, $error1): ListOfErrors {
            $this->assertSame($message, $actual);
            return new ListOfErrors($error1);
        };

        $b = function ($actual) use ($message): ?ListOfErrors {
            $this->assertSame($message, $actual);
            return null;
        };

        $c = function ($actual) use ($message, $error2, $error3): ListOfErrors {
            $this->assertSame($message, $actual);
            return new ListOfErrors($error2, $error3);
        };

        $rules = new PsrPipeContainer();
        $rules->bind('Rule2', fn () => $b);
        $rules->bind('Rule3', fn () => $c);

        $validator = new Validator(rules: $rules)->using([$a, 'Rule2', 'Rule3']);

        $actual = $validator->validate($message);

        $this->assertEquals(new ListOfErrors($error1, $error2, $error3), $actual);
    }

    /**
     * @param class-string<Message> $class
     */
    #[DataProvider('messageProvider')]
    #[Test]
    public function it_validates_message_using_psr_container(string $class): void
    {
        $message = $this->createStub($class);
        $error1 = new Error(null, 'Message 1');
        $error2 = new Error(null, 'Message 2');
        $error3 = new Error(null, 'Message 3');

        $a = function ($actual) use ($message, $error1): Error {
            $this->assertSame($message, $actual);
            return $error1;
        };

        $b = function ($actual) use ($message): ?ListOfErrors {
            $this->assertSame($message, $actual);
            return null;
        };

        $c = function ($actual) use ($message, $error2, $error3): ListOfErrors {
            $this->assertSame($message, $actual);
            return new ListOfErrors($error2, $error3);
        };

        $rules = new FakeContainer();
        $rules->instance('Rule2', $b);
        $rules->instance('Rule3', $c);

        $validator = new Validator(rules: $rules)->using([$a, 'Rule2', 'Rule3']);

        $actual = $validator->validate($message);

        $this->assertEquals(new ListOfErrors($error1, $error2, $error3), $actual);
    }

    /**
     * @param class-string<Message> $class
     */
    #[DataProvider('messageProvider')]
    #[Test]
    public function it_stops_on_first_failure(string $class): void
    {
        $message = $this->createStub($class);
        $error = new Error(null, 'Message 1');

        $a = function ($actual) use ($message): null {
            $this->assertSame($message, $actual);
            return null;
        };

        $b = function ($actual) use ($message, $error): ListOfErrors {
            $this->assertSame($message, $actual);
            return new ListOfErrors($error);
        };

        $c = function (): never {
            $this->fail('Not expecting to be called.');
        };

        $validator = new Validator()
            ->using([$a, $b, $c])
            ->stopOnFirstFailure();

        $actual = $validator->validate($message);

        $this->assertEquals(new ListOfErrors($error), $actual);
    }

    #[Test]
    public function it_has_no_rules(): void
    {
        $query = $this->createStub(Query::class);
        $validator = new Validator();

        $actual = $validator->validate($query);

        $this->assertEquals(new ListOfErrors(), $actual);
    }
}
