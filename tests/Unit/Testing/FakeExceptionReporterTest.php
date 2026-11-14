<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Testing;

use CloudCreativity\Modules\Contracts\Application\Ports\ExceptionReporter;
use CloudCreativity\Modules\Testing\FakeExceptionReporter;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FakeExceptionReporterTest extends TestCase
{
    private FakeExceptionReporter $reporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporter = new FakeExceptionReporter();
    }

    protected function tearDown(): void
    {
        unset($this->reporter);
        parent::tearDown();
    }

    #[Test]
    public function it_is_an_exception_reporter(): void
    {
        $this->assertInstanceOf(ExceptionReporter::class, $this->reporter);
    }

    #[Test]
    public function it_is_empty_by_default(): void
    {
        $actual = [count($this->reporter), iterator_to_array($this->reporter)];

        $this->assertSame([0, []], $actual);
    }

    // -------------------------------------------------------------------------
    // report()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_reports_exceptions(): void
    {
        $ex1 = new RuntimeException('Whoops!');
        $ex2 = new RuntimeException('Whoops again!');

        $this->reporter->report($ex1);
        $this->reporter->report($ex2);

        $this->assertSame([$ex1, $ex2], $this->reporter->reported);
    }

    // -------------------------------------------------------------------------
    // none() and expect()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_does_not_expect_exceptions(): void
    {
        $expected = new RuntimeException('Boom! That was a surprise.');
        $this->reporter->none();

        $this->expectExceptionObject($expected);

        $this->reporter->report($expected);
    }

    #[Test]
    public function it_records_exceptions_until_the_expected_count_is_reached(): void
    {
        $ex1 = new RuntimeException('Whoops!');
        $ex2 = new RuntimeException('Whoops again!');
        $this->reporter->reported = [$ex1];
        $this->reporter->expect(2);

        $this->reporter->report($ex2);

        $this->assertSame([$ex1, $ex2], $this->reporter->reported);
    }

    #[Test]
    public function it_rethrows_once_the_expected_count_is_reached(): void
    {
        $expected = new RuntimeException('Boom! That was a surprise.');
        $this->reporter->reported = [
            new LogicException('Whoops 0!'),
            new LogicException('Whoops 1!'),
            new LogicException('Whoops 2!'),
        ];
        $this->reporter->expect(3);

        $this->expectExceptionObject($expected);

        $this->reporter->report($expected);
    }

    #[Test]
    public function it_rethrows_when_the_expected_count_has_been_exceeded(): void
    {
        $expected = new RuntimeException('Boom! That was a surprise.');
        $this->reporter->reported = [
            new LogicException('Whoops 0!'),
            new LogicException('Whoops 1!'),
        ];
        $this->reporter->expect(1);

        $this->expectExceptionObject($expected);

        $this->reporter->report($expected);
    }

    // -------------------------------------------------------------------------
    // count() and getIterator()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_counts_the_reported_exceptions(): void
    {
        $this->reporter->reported = [
            new RuntimeException('Whoops!'),
            new RuntimeException('Whoops again!'),
        ];

        $actual = count($this->reporter);

        $this->assertSame(2, $actual);
    }

    #[Test]
    public function it_iterates_over_the_reported_exceptions(): void
    {
        $ex1 = new RuntimeException('Whoops!');
        $ex2 = new RuntimeException('Whoops again!');
        $this->reporter->reported = [$ex1, $ex2];

        $actual = iterator_to_array($this->reporter);

        $this->assertSame([$ex1, $ex2], $actual);
    }

    // -------------------------------------------------------------------------
    // ArrayAccess
    // -------------------------------------------------------------------------

    #[Test]
    public function it_gets_an_exception_by_offset(): void
    {
        $ex1 = new RuntimeException('Whoops!');
        $ex2 = new RuntimeException('Whoops again!');
        $this->reporter->reported = [$ex1, $ex2];

        $actual = [$this->reporter[0], $this->reporter[1]];

        $this->assertSame([$ex1, $ex2], $actual);
    }

    #[Test]
    public function it_checks_whether_an_offset_exists(): void
    {
        $this->reporter->reported = [new RuntimeException('Whoops!')];

        $actual = [isset($this->reporter[0]), isset($this->reporter[1])];

        $this->assertSame([true, false], $actual);
    }

    #[Test]
    public function it_cannot_set_an_offset(): void
    {
        $ex = new RuntimeException('Whoops!');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot set reported exceptions.');

        $this->reporter[0] = $ex;
    }

    #[Test]
    public function it_cannot_unset_an_offset(): void
    {
        $this->reporter->reported = [new RuntimeException('Whoops!')];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot unset reported exceptions.');

        unset($this->reporter[0]);
    }

    // -------------------------------------------------------------------------
    // sole()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_has_sole_exception(): void
    {
        $ex = new RuntimeException('Whoops!');
        $this->reporter->reported = [$ex];

        $actual = $this->reporter->sole();

        $this->assertSame($ex, $actual);
    }

    #[Test]
    public function it_fails_when_there_is_no_sole_exception(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected one exception to be reported but there are 0 exceptions.');

        $this->reporter->sole();
    }

    #[Test]
    public function it_fails_when_there_is_more_than_one_sole_exception(): void
    {
        $this->reporter->reported = [
            new RuntimeException('Whoops!'),
            new RuntimeException('Whoops again!'),
        ];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected one exception to be reported but there are 2 exceptions.');

        $this->reporter->sole();
    }
}
