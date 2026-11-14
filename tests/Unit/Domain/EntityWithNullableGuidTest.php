<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Domain;

use CloudCreativity\Modules\Contracts\Domain\Entity;
use CloudCreativity\Modules\Contracts\Toolkit\Identifiers\Identifier;
use CloudCreativity\Modules\Toolkit\ContractException;
use CloudCreativity\Modules\Toolkit\Identifiers\Guid;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EntityWithNullableGuidTest extends TestCase
{
    #[Test]
    public function it_has_an_id(): void
    {
        $entity = new TestEntityWithNullableId(
            $guid = Guid::fromInteger('SomeType', 1),
        );

        $this->assertSame($guid, $entity->getId());
        $this->assertSame($guid, $entity->getIdOrFail());
        $this->assertFalse($entity->is(null));
        $this->assertTrue($entity->isNot(null));
        $this->assertTrue($entity->hasId());
    }

    #[Test]
    public function it_has_a_null_id(): TestEntityWithNullableId
    {
        $entity = new TestEntityWithNullableId();

        $this->assertNull($entity->getId());
        $this->assertFalse($entity->is(null));
        $this->assertTrue($entity->isNot(null));
        $this->assertFalse($entity->hasId());

        return $entity;
    }

    #[Depends('it_has_a_null_id')]
    #[Test]
    public function it_fails_to_get_the_id_when_it_is_null(TestEntityWithNullableId $entity): void
    {
        $this->expectException(\AssertionError::class);
        $this->expectExceptionMessage('Entity does not have an identifier.');

        $entity->getIdOrFail();
    }

    #[Test]
    public function it_sets_the_id(): void
    {
        $entity = new TestEntityWithNullableId();
        $actual = $entity->setId($guid = Guid::fromInteger('SomeType', 99));

        $this->assertSame($entity, $actual);
        $this->assertSame($guid, $entity->getId());
    }

    #[Test]
    public function it_cannot_set_the_id_when_the_entity_already_has_one(): void
    {
        $entity = new TestEntityWithNullableId(Guid::fromInteger('SomeType', 1));

        $this->expectException(ContractException::class);
        $entity->setId(Guid::fromInteger('SomeType', 2));
    }

    #[Test]
    public function it_is_the_same(): void
    {
        $a = new TestEntityWithNullableId(
            $id1 = $this->createMock(Identifier::class),
        );

        $b = new TestEntityWithNullableId(
            $id2 = $this->createStub(Identifier::class),
        );

        $id1
            ->expects($this->exactly(2))
            ->method('is')
            ->with($this->identicalTo($id2))
            ->willReturn(true);

        $this->assertTrue($a->is($b));
        $this->assertFalse($a->isNot($b));
    }

    #[Test]
    public function it_is_not_the_same_with_null_id(): void
    {
        $a = new TestEntityWithNullableId();

        $b = new TestEntityWithNullableId(
            $id1 = $this->createMock(Identifier::class),
        );

        $id1
            ->expects($this->never())
            ->method('is');

        $this->assertFalse($a->is($b));
        $this->assertTrue($a->isNot($b));
    }

    #[Test]
    public function it_is_the_same_when_other_has_null_id(): void
    {
        $a = new TestEntityWithNullableId(
            $id1 = $this->createMock(Identifier::class),
        );

        $b = new TestEntityWithNullableId();

        $id1
            ->expects($this->exactly(2))
            ->method('is')
            ->with(null)
            ->willReturn(false);

        $this->assertFalse($a->is($b));
        $this->assertTrue($a->isNot($b));
    }

    #[Test]
    public function it_is_not_the_same(): void
    {
        $a = new TestEntityWithNullableId(
            $id1 = $this->createMock(Identifier::class),
        );

        $b = new TestEntityWithNullableId(
            $id2 = $this->createStub(Identifier::class),
        );

        $id1
            ->expects($this->exactly(2))
            ->method('is')
            ->with($this->identicalTo($id2))
            ->willReturn(false);

        $this->assertFalse($a->is($b));
        $this->assertTrue($a->isNot($b));
    }

    #[Test]
    public function it_is_different_class(): void
    {
        $a = new TestEntityWithNullableId(
            $id1 = $this->createMock(Identifier::class),
        );

        $b = $this->createStub(Entity::class);
        $b->method('getId')->willReturn($this->createStub(Identifier::class));

        $id1
            ->expects($this->never())
            ->method('is');

        $this->assertFalse($a->is($b));
        $this->assertTrue($a->isNot($b));
    }
}
