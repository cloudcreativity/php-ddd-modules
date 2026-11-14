<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Toolkit\Identifiers;

use CloudCreativity\Modules\Contracts\Toolkit\Identifiers\Identifier;
use CloudCreativity\Modules\Toolkit\ContractException;
use CloudCreativity\Modules\Toolkit\Identifiers\Uuid;
use CloudCreativity\Modules\Toolkit\Identifiers\UuidFactory;
use CloudCreativity\Modules\Toolkit\Identifiers\UuidV4;
use CloudCreativity\Modules\Toolkit\Identifiers\UuidV7;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Type\Hexadecimal;
use Ramsey\Uuid\Type\Integer;
use Ramsey\Uuid\Uuid as BaseUuid;
use Ramsey\Uuid\UuidFactory as BaseUuidFactory;
use Ramsey\Uuid\UuidFactoryInterface as BaseUuidFactoryInterface;

final class UuidFactoryTest extends TestCase
{
    private BaseUuidFactory&MockObject $baseFactory;

    private UuidFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new UuidFactory(
            $this->baseFactory = $this->createMock(BaseUuidFactory::class),
        );
    }

    protected function tearDown(): void
    {
        unset($this->factory, $this->baseFactory);
        parent::tearDown();
    }

    #[Test]
    public function it_wraps_a_base_uuid(): void
    {
        $this->baseFactory
            ->expects($this->never())
            ->method($this->anything());

        $uuid = $this->factory->from($base = BaseUuid::uuid4());

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($base, $uuid->value);
    }

    #[Test]
    public function it_returns_the_same_uuid_instance(): void
    {
        $this->baseFactory
            ->expects($this->never())
            ->method($this->anything());

        $actual = $this->factory->from($expected = Uuid::random());

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_rejects_a_non_uuid_identifier(): void
    {
        $this->baseFactory
            ->expects($this->never())
            ->method($this->anything());

        $this->expectException(ContractException::class);

        $this->factory->from(
            $this->createMock(Identifier::class),
        );
    }

    #[Test]
    public function it_creates_from_bytes(): void
    {
        $this->baseFactory
            ->expects($this->once())
            ->method('fromBytes')
            ->with($bytes = 'blah!')
            ->willReturn($base = BaseUuid::uuid4());

        $uuid = $this->factory->fromBytes($bytes);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($uuid->value, $base);
    }

    #[Test]
    public function it_creates_from_a_date_time(): void
    {
        $this->baseFactory
            ->expects($this->once())
            ->method('fromDateTime')
            ->with(
                $this->identicalTo($dateTime = new DateTimeImmutable('2021-10-10')),
                $this->identicalTo($hex = new Hexadecimal('8f0b0445be')),
                $int = 99,
            )
            ->willReturn($base = BaseUuid::uuid4());

        $uuid = $this->factory->fromDateTime($dateTime, $hex, $int);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($uuid->value, $base);
    }

    #[Test]
    public function it_creates_from_an_integer(): void
    {
        $this->baseFactory
            ->expects($this->once())
            ->method('fromInteger')
            ->with($integer = '123')
            ->willReturn($base = BaseUuid::uuid4());

        $uuid = $this->factory->fromInteger($integer);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($uuid->value, $base);
    }

    #[Test]
    public function it_creates_from_a_string(): void
    {
        $this->baseFactory
            ->expects($this->once())
            ->method('fromString')
            ->with($string = 'foobar')
            ->willReturn($base = BaseUuid::uuid4());

        $uuid = $this->factory->fromString($string);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($uuid->value, $base);
    }

    #[Test]
    public function it_creates_a_uuid_v1(): void
    {
        $this->baseFactory
            ->expects($this->once())
            ->method('uuid1')
            ->with(
                $this->identicalTo($hex = new Hexadecimal('8f0b0445be')),
                $int = 99,
            )
            ->willReturn($base = BaseUuid::uuid4());

        $uuid = $this->factory->uuid1($hex, $int);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($uuid->value, $base);
    }

    #[Test]
    public function it_creates_a_uuid_v2(): void
    {
        $this->baseFactory
            ->expects($this->once())
            ->method('uuid2')
            ->with(
                $int = 99,
                $this->identicalTo($integer = new Integer(123)),
                $this->identicalTo($hex = new Hexadecimal('8f0b0445be')),
                $seq = 999,
            )
            ->willReturn($base = BaseUuid::uuid4());

        $uuid = $this->factory->uuid2($int, $integer, $hex, $seq);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($uuid->value, $base);
    }

    #[Test]
    public function it_creates_a_uuid_v3(): void
    {
        $this->baseFactory
            ->expects($this->once())
            ->method('uuid3')
            ->with(
                $this->identicalTo($ns = BaseUuid::uuid4()),
                $name = 'foobar',
            )
            ->willReturn($base = BaseUuid::uuid4());

        $uuid = $this->factory->uuid3($ns, $name);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($uuid->value, $base);
    }

    #[Test]
    public function it_creates_a_uuid_v4(): void
    {
        $this->baseFactory
            ->expects($this->once())
            ->method('uuid4')
            ->willReturn($base = BaseUuid::uuid4());

        $uuid = $this->factory->uuid4();

        $this->assertInstanceOf(UuidV4::class, $uuid);
        $this->assertSame($uuid->value, $base);
    }

    #[Test]
    public function it_creates_a_uuid_v5(): void
    {
        $this->baseFactory
            ->expects($this->once())
            ->method('uuid5')
            ->with(
                $this->identicalTo($ns = BaseUuid::uuid4()),
                $name = 'foobar',
            )
            ->willReturn($base = BaseUuid::uuid4());

        $uuid = $this->factory->uuid5($ns, $name);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($uuid->value, $base);
    }

    #[Test]
    public function it_creates_a_uuid_v6(): void
    {
        $this->baseFactory
            ->expects($this->once())
            ->method('uuid6')
            ->with(
                $this->identicalTo($hex = new Hexadecimal('8f0b0445be')),
                $int = 99,
            )
            ->willReturn($base = BaseUuid::uuid4());

        $uuid = $this->factory->uuid6($hex, $int);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($uuid->value, $base);
    }

    #[Test]
    public function it_creates_a_uuid_v7(): void
    {
        $date = new \DateTimeImmutable();

        $this->baseFactory
            ->expects($this->once())
            ->method('uuid7')
            ->with($this->identicalTo($date))
            ->willReturn($base = BaseUuid::uuid7());

        $uuid = $this->factory->uuid7($date);

        $this->assertInstanceOf(UuidV7::class, $uuid);
        $this->assertSame($uuid->value, $base);
    }

    #[Test]
    public function it_throws_if_uuid_v7_is_not_supported(): void
    {
        $factory = new UuidFactory(
            $this->createMock(BaseUuidFactoryInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('UUID version 7 is not supported by the underlying factory.');

        $factory->uuid7();
    }

    #[Test]
    public function it_creates_a_uuid_v8(): void
    {
        $bytes = 'blah!';

        $this->baseFactory
            ->expects($this->once())
            ->method('uuid8')
            ->with($bytes)
            ->willReturn($base = BaseUuid::uuid4());

        $uuid = $this->factory->uuid8($bytes);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($uuid->value, $base);
    }

    #[Test]
    public function it_throws_if_uuid_v8_is_not_supported(): void
    {
        $factory = new UuidFactory(
            $this->createMock(BaseUuidFactoryInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('UUID version 8 is not supported by the underlying factory.');

        $factory->uuid8('bytes!');
    }
}
