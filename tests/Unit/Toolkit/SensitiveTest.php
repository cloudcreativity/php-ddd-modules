<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Toolkit;

use Attribute;
use CloudCreativity\Modules\Toolkit\Sensitive;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class SensitiveTest extends TestCase
{
    #[Test]
    public function it_can_be_applied_to_a_property(): void
    {
        $object = new class () {
            #[Sensitive]
            public string $secret = 'secret';
        };
        $reflection = new ReflectionProperty($object, 'secret');

        $attributes = $reflection->getAttributes(Sensitive::class);

        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(Sensitive::class, $attributes[0]->newInstance());
    }

    #[Test]
    public function it_only_targets_properties(): void
    {
        $reflection = new ReflectionClass(Sensitive::class);

        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        $this->assertSame(Attribute::TARGET_PROPERTY, $attribute->flags);
    }
}
