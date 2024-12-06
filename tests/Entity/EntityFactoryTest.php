<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Entity\EntityCache;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Entity\EntityReflection;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\EntitySchemaFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityFactory::class)]
#[UsesClass(EntityReflection::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(SchemaProvider::class)]
final class EntityFactoryTest extends TestCase
{
    public function testCreateEntity(): void
    {
        $entityCache = $this->createMock(EntityCache::class);
        $entityCache->method('getEntity')
            ->willReturn(null);
        $mapper = $this->createMock(Mapper::class);
        $mapper->method('mapToProperty')
            ->willReturn(
                new DateTimeImmutable('2024-01-01 00:00'),
                'John',
                null,
                'Doe',
                'johh.doe@example.com',
                true,
                UserTypeEnum::Admin,
                1,
            );
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $schemaProvider->method('getPrimaryColumnSchema')
            ->willReturn(new ColumnSchema('id', PropertyTypeEnum::Int, 'id', 'int'));
        $schemaProvider->method('getEntitySchema')
            ->willReturn(EntitySchemaFixture::create());

        $entityFactory = new EntityFactory(
            $schemaProvider,
            $entityCache,
            new EntityReflection(),
        );
        $entityFactory->setMapper($mapper);
        $entity = $entityFactory->create(UserFixture::class, [
            'id' => 1,
            'created_at' => '2024-01-01 00:00',
            'first_name' => 'John',
            'middle_name' => null,
            'last_name' => 'Doe',
            'email' => 'johh.doe@example.com',
            'is_active' => 1,
            'type' => 'admin',
        ]);

        self::assertInstanceOf(UserFixture::class, $entity);
        self::assertEquals('John', $entity->firstName);
        self::assertEquals(null, $entity->middleName);
        self::assertEquals('Doe', $entity->lastName);
        self::assertEquals('johh.doe@example.com', $entity->email);
        self::assertEquals(true, $entity->isActive);
    }
}
