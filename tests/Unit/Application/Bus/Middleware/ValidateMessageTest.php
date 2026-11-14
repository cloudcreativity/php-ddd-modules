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
use CloudCreativity\Modules\Contracts\Bus\Validation\Bail;
use CloudCreativity\Modules\Contracts\Bus\Validation\Validator;
use CloudCreativity\Modules\Contracts\Messaging\Command;
use CloudCreativity\Modules\Contracts\Messaging\Message;
use CloudCreativity\Modules\Contracts\Messaging\Query;
use CloudCreativity\Modules\Toolkit\Result\Error;
use CloudCreativity\Modules\Toolkit\Result\ListOfErrors;
use CloudCreativity\Modules\Toolkit\Result\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ValidateMessageTest extends TestCase
{
    private MockObject&Validator $validator;

    private ValidateMessage $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = $this->createMock(Validator::class);

        $this->middleware = new class ($this->validator) extends ValidateMessage {
            /**
             * @return iterable<string>
             */
            protected function rules(): iterable
            {
                return ['foo', 'bar'];
            }
        };
    }

    protected function tearDown(): void
    {
        unset($this->validator, $this->middleware);
        parent::tearDown();
    }

    #[Test]
    public function it_succeeds(): void
    {
        $rules = [];
        $command = $this->createStub(Command::class);
        $expected = Result::ok();

        $next = function ($actual) use ($command, $expected) {
            $this->assertSame($command, $actual);
            return $expected;
        };

        $this->validator
            ->expects($this->once())
            ->method('using')
            ->with($this->callback(function (array $actual) use (&$rules): bool {
                $rules = $actual;
                return true;
            }))
            ->willReturnSelf();

        $this->validator
            ->expects($this->once())
            ->method('stopOnFirstFailure')
            ->willReturnSelf();

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($this->callback(function (Command $actual) use ($command, &$rules): bool {
                $this->assertSame(['foo', 'bar'], $rules);
                $this->assertSame($command, $actual);
                return true;
            }))
            ->willReturn(new ListOfErrors());

        $actual = ($this->middleware)($command, $next);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_fails(): void
    {
        $command = $this->createStub(Query::class);
        $errors = new ListOfErrors(new Error(null, 'Something went wrong.'));

        $next = function () {
            throw new \LogicException('Not expecting next closure to be called.');
        };

        $this->validator
            ->expects($this->once())
            ->method('using')
            ->willReturnSelf();

        $this->validator
            ->expects($this->once())
            ->method('stopOnFirstFailure')
            ->with(false)
            ->willReturnSelf();

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($command))
            ->willReturn($errors);

        $actual = ($this->middleware)($command, $next);

        $this->assertEquals(Result::failed($errors), $actual);
    }

    #[Test]
    public function it_stops_on_first_failure_via_bail(): void
    {
        $command = $this->createStub(Message::class);
        $errors = new ListOfErrors(new Error(null, 'Something went wrong.'));
        $middleware = new class ($this->validator) extends ValidateMessage implements Bail {
            /**
             * @return iterable<string>
             */
            protected function rules(): iterable
            {
                return ['foo', 'bar'];
            }
        };

        $next = function () {
            throw new \LogicException('Not expecting next closure to be called.');
        };

        $this->validator
            ->expects($this->once())
            ->method('using')
            ->willReturnSelf();

        $this->validator
            ->expects($this->once())
            ->method('stopOnFirstFailure')
            ->with(true)
            ->willReturnSelf();

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($command))
            ->willReturn($errors);

        $actual = $middleware($command, $next);

        $this->assertEquals(Result::failed($errors), $actual);
    }

    #[Test]
    public function it_stops_on_first_failure(): void
    {
        $command = $this->createStub(Command::class);
        $errors = new ListOfErrors(new Error(null, 'Something went wrong.'));
        $middleware = new class ($command, $this->validator) extends ValidateMessage {
            public function __construct(private Message $message, Validator $validator)
            {
                parent::__construct($validator);
            }

            /**
             * @return iterable<string>
             */
            protected function rules(): iterable
            {
                return ['foo', 'bar'];
            }

            protected function stopOnFirstFailure(Message $message): bool
            {
                return $this->message === $message;
            }
        };

        $next = function () {
            throw new \LogicException('Not expecting next closure to be called.');
        };

        $this->validator
            ->expects($this->once())
            ->method('using')
            ->willReturnSelf();

        $this->validator
            ->expects($this->once())
            ->method('stopOnFirstFailure')
            ->with(true)
            ->willReturnSelf();

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($command))
            ->willReturn($errors);

        $actual = $middleware($command, $next);

        $this->assertEquals(Result::failed($errors), $actual);
    }

    #[Test]
    public function it_uses_rules_from_the_with_rules_attribute(): void
    {
        $command = $this->createStub(Command::class);
        $expected = Result::ok();
        $middleware = new TestValidateMessageWithRules($this->validator);

        $this->validator
            ->expects($this->once())
            ->method('using')
            ->with($this->callback(
                fn (iterable $rules): bool => ['Rule1', 'Rule2'] === iterator_to_array($rules, false),
            ))
            ->willReturnSelf();

        $this->validator
            ->expects($this->once())
            ->method('stopOnFirstFailure')
            ->with(false)
            ->willReturnSelf();

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($command))
            ->willReturn(new ListOfErrors());

        $actual = $middleware($command, fn () => $expected);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_has_no_rules_by_default(): void
    {
        $command = $this->createStub(Command::class);
        $expected = Result::ok();
        $middleware = new class ($this->validator) extends ValidateMessage {};

        $this->validator
            ->expects($this->once())
            ->method('using')
            ->with($this->callback(fn (iterable $rules): bool => [] === iterator_to_array($rules, false)))
            ->willReturnSelf();

        $this->validator
            ->expects($this->once())
            ->method('stopOnFirstFailure')
            ->with(false)
            ->willReturnSelf();

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($command))
            ->willReturn(new ListOfErrors());

        $actual = $middleware($command, fn () => $expected);

        $this->assertSame($expected, $actual);
    }
}
