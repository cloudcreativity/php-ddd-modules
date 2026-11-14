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
use CloudCreativity\Modules\Toolkit\ContractException;
use CloudCreativity\Modules\Toolkit\Identifiers\Guid;
use CloudCreativity\Modules\Domain\IdentifierOrEntity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IdentifierOrEntityTest extends TestCase
{
    #[Test]
    public function it_is_a_guid(): void
    {
        $guidOrEntity = IdentifierOrEntity::make($guid = Guid::fromInteger('SomeType', 1));

        $this->assertSame($guid, $guidOrEntity->id());
        $this->assertSame($guid, $guidOrEntity->idOrFail());
        $this->assertNull($guidOrEntity->entity);
    }

    #[Test]
    public function it_is_an_entity_with_guid(): void
    {
        $entity = $this->createStub(Entity::class);
        $entity->method('getId')->willReturn($guid = Guid::fromInteger('SomeType', 1));

        $guidOrEntity = IdentifierOrEntity::make($entity);

        $this->assertSame($guid, $guidOrEntity->id());
        $this->assertSame($guid, $guidOrEntity->idOrFail());
        $this->assertSame($entity, $guidOrEntity->entity);
    }

    #[Test]
    public function it_is_an_entity_without_guid(): void
    {
        $entity = $this->createStub(Entity::class);
        $entity->method('getId')->willReturn(null);

        $guidOrEntity = IdentifierOrEntity::make($entity);

        $this->assertNull($guidOrEntity->id());
        $this->assertSame($entity, $guidOrEntity->entity);
    }

    #[Test]
    public function it_fails_to_get_the_id_when_the_entity_does_not_have_one(): void
    {
        $entity = $this->createStub(Entity::class);
        $entity->method('getId')->willReturn(null);
        $guidOrEntity = IdentifierOrEntity::make($entity);

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Entity does not have an identifier.');

        $guidOrEntity->idOrFail();
    }
}
