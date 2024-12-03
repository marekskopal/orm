<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Factory;

use MarekSkopal\ORM\Entity\EntityCache;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Entity\EntityReflection;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\SchemaFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityFactory::class)]
final class EntityFactoryTest extends TestCase
{
    public function testCreateEntity(): void
    {
        $entityCache = $this->createMock(EntityCache::class);
        $entityCache->method('getEntity')
            ->willReturn(null);
        $mapper = $this->createMock(Mapper::class);
        $mapper->method('mapColumn')
            ->willReturn(1, 'John', 'Doe', 'johh.doe@example.com', true);

        $entityFactory = new EntityFactory(
            SchemaFixture::create(),
            $entityCache,
            new EntityReflection(),
        );
        $entity = $entityFactory->create(UserFixture::class, [
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johh.doe@example.com',
            'is_active' => 1,
        ], $mapper);

        self::assertInstanceOf(UserFixture::class, $entity);
        self::assertEquals('John', $entity->firstName);
        self::assertEquals('Doe', $entity->lastName);
        self::assertEquals('johh.doe@example.com', $entity->email);
        self::assertEquals(true, $entity->isActive);
    }
}
