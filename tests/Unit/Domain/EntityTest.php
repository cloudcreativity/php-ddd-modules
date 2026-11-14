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
use CloudCreativity\Modules\Toolkit\Identifiers\Guid;
use CloudCreativity\Modules\Toolkit\Identifiers\IntegerId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EntityTest extends TestCase
{
    #[Test]
    public function it_has_an_id(): void
    {
        $entity = new TestEntity($guid = Guid::fromInteger('SomeType', 1));

        $this->assertSame($guid, $entity->getId());
        $this->assertSame($guid, $entity->getIdOrFail());
        $this->assertFalse($entity->is(null));
        $this->assertTrue($entity->isNot(null));
    }

    #[Test]
    public function it_is_the_same(): void
    {
        $a = new TestEntity(new IntegerId(1));
        $b = new TestEntity(new IntegerId(1));

        $this->assertTrue($a->is($b));
        $this->assertFalse($a->isNot($b));
    }

    #[Test]
    public function it_is_not_the_same(): void
    {
        $a = new TestEntity(new IntegerId(1));
        $b = new TestEntity(new IntegerId(2));

        $this->assertFalse($a->is($b));
        $this->assertTrue($a->isNot($b));
    }

    #[Test]
    public function it_is_different_class(): void
    {
        $a = new TestEntity(new IntegerId(1));
        $b = $this->createStub(Entity::class);
        $b->method('getId')->willReturn(new IntegerId(1));

        $this->assertFalse($a->is($b));
        $this->assertTrue($a->isNot($b));
    }

    #[Test]
    public function it_can_use_trait_on_non_readonly_class(): void
    {
        $entity = new MutableTestEntity($id = new IntegerId(123), 'Bob');

        $this->assertSame($id, $entity->getId());
        $this->assertSame('Bob', $entity->getName());
    }

    #[Test]
    public function it_can_change_state_on_non_readonly_class(): void
    {
        $entity = new MutableTestEntity(new IntegerId(123), 'Bob');

        $entity->changeName('Chris');

        $this->assertSame('Chris', $entity->getName());
    }
}
