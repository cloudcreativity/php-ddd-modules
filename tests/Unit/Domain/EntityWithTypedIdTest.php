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
use CloudCreativity\Modules\Domain\IsEntityWithTypedId;
use CloudCreativity\Modules\Toolkit\Identifiers\IntegerId;
use CloudCreativity\Modules\Toolkit\Identifiers\UuidV7;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EntityWithTypedIdTest extends TestCase
{
    #[Test]
    public function it_has_a_typed_id(): void
    {
        $entity = new TestEntityWithTypedId($id = UuidV7::make());

        $this->assertSame($id, $entity->id);
        $this->assertSame($id, $entity->getId());
        $this->assertSame($id, $entity->getIdOrFail());
        $this->assertFalse($entity->is(null));
        $this->assertTrue($entity->isNot(null));
    }

    #[Test]
    public function it_is_the_same(): void
    {
        $a = new TestEntityWithTypedId(
            $id1 = UuidV7::make(),
        );

        $b = new TestEntityWithTypedId(
            UuidV7::from($id1->toString()),
        );

        $this->assertTrue($a->is($b));
        $this->assertFalse($a->isNot($b));
    }

    #[Test]
    public function it_is_not_the_same(): void
    {
        $a = new TestEntityWithTypedId(UuidV7::make());
        $b = new TestEntityWithTypedId(UuidV7::make());

        $this->assertFalse($a->is($b));
        $this->assertTrue($a->isNot($b));
    }

    #[Test]
    public function it_is_different_class(): void
    {
        $a = new TestEntityWithTypedId(UuidV7::make());
        $b = $this->createMock(Entity::class);
        $b->method('getId')->willReturn(UuidV7::from($a->id->toString()));

        $this->assertFalse($a->is($b));
        $this->assertTrue($a->isNot($b));
    }

    #[Test]
    public function it_can_use_trait_on_non_readonly_class(): void
    {
        $id = new IntegerId(123);

        $entity = new class ($id, 'Bob') implements Entity {
            use IsEntityWithTypedId;

            public function __construct(public IntegerId $id, public private(set) string $name)
            {
            }

            public function changeName(string $name): void
            {
                $this->name = $name;
            }
        };

        $this->assertSame($id, $entity->getId());
        $this->assertSame('Bob', $entity->name);
        $entity->changeName('Chris');
        $this->assertSame('Chris', $entity->name);
    }
}
