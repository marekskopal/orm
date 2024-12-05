<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Mapper;

use Iterator;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\EntitySchemaFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Lazy\LazyUuidFromString;
use Ramsey\Uuid\Uuid;

#[CoversClass(Mapper::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
final class MapperTest extends TestCase
{
    public function testMapColumnString(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('name', PropertyTypeEnum::String, 'name', 'varchar');
        $entitySchema = EntitySchemaFixture::create(columns: ['name' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $queryProvider);
        $result = $mapper->mapColumn($entitySchema, $columnSchema, 'test');
        self::assertSame('test', $result);
    }

    public function testMapColumnInt(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('age', PropertyTypeEnum::Int, 'age', 'int');
        $entitySchema = EntitySchemaFixture::create(columns: ['age' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $queryProvider);
        $result = $mapper->mapColumn($entitySchema, $columnSchema, 25);
        self::assertSame(25, $result);
    }

    public function testMapColumnFloat(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('price', PropertyTypeEnum::Float, 'price', 'float');
        $entitySchema = EntitySchemaFixture::create(columns: ['price' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $queryProvider);
        $result = $mapper->mapColumn($entitySchema, $columnSchema, 19.99);
        self::assertSame(19.99, $result);
    }

    public function testMapColumnBool(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('isActive', PropertyTypeEnum::Bool, 'is_active', 'tinyint');
        $entitySchema = EntitySchemaFixture::create(columns: ['isActive' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $queryProvider);
        $result = $mapper->mapColumn($entitySchema, $columnSchema, 1);
        self::assertTrue($result);
    }

    public function testMapColumnUuid(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('code', PropertyTypeEnum::Uuid, 'code', 'uuid');
        $entitySchema = EntitySchemaFixture::create(columns: ['code' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $queryProvider);
        $result = $mapper->mapColumn($entitySchema, $columnSchema, 'f47ac10b-58cc-4372-a567-0e02b2c3d479');
        self::assertInstanceOf(LazyUuidFromString::class, $result);
        self::assertSame((string) Uuid::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'), (string) $result);
    }

    public function testMapColumnRelationManyToOne(): void
    {
        $primaryColumnSchema = new ColumnSchema('id', PropertyTypeEnum::Int, 'int', 'int', isPrimary: true);
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $schemaProvider->method('getPrimaryColumnSchema')->willReturn($primaryColumnSchema);
        $select = $this->createMock(Select::class);
        $select->method('where')->willReturnSelf();
        $select->method('fetch')->willReturn(UserFixture::create());
        $queryProvider = $this->createMock(QueryProvider::class);
        $queryProvider->method('select')->willReturn($select);

        $mapper = new Mapper($schemaProvider, $queryProvider);

        $columnSchema = new ColumnSchema('user', PropertyTypeEnum::Relation, 'user_id', 'int', RelationEnum::ManyToOne, UserFixture::class);
        $entitySchema = EntitySchemaFixture::create(columns: ['user' => $columnSchema]);

        $result = $mapper->mapColumn($entitySchema, $columnSchema, 1);
        self::assertInstanceOf(UserFixture::class, $result);
    }

    public function testMapColumnRelationOneToMany(): void
    {
        $primaryColumnSchema = new ColumnSchema('id', PropertyTypeEnum::Int, 'int', 'int', isPrimary: true);
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $schemaProvider->method('getPrimaryColumnSchema')->willReturn($primaryColumnSchema);
        $select = $this->createMock(Select::class);
        $select->method('where')->willReturnSelf();
        $select->method('fetch')->willReturn(UserFixture::create());
        $queryProvider = $this->createMock(QueryProvider::class);
        $queryProvider->method('select')->willReturn($select);

        $mapper = new Mapper($schemaProvider, $queryProvider);

        $columnSchema = new ColumnSchema('users', PropertyTypeEnum::Relation, 'users', 'int', RelationEnum::OneToMany, UserFixture::class);
        $entitySchema = EntitySchemaFixture::create(columns: ['users' => $columnSchema]);

        $result = $mapper->mapColumn($entitySchema, $columnSchema, 1);
        self::assertInstanceOf(Iterator::class, $result);
    }

    public function testMapColumnRelationNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Entity "MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture" with id "1" not found');

        $columnSchema = new ColumnSchema('user', PropertyTypeEnum::Relation, 'user_id', 'int', RelationEnum::ManyToOne, UserFixture::class);
        $entitySchema = EntitySchemaFixture::create(columns: ['user' => $columnSchema]);

        $primaryColumnSchema = new ColumnSchema('id', PropertyTypeEnum::Int, 'int', 'int', isPrimary: true);
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $schemaProvider->method('getPrimaryColumnSchema')->willReturn($primaryColumnSchema);
        $select = $this->createMock(Select::class);
        $select->method('where')->willReturnSelf();
        $select->method('fetch')->willReturn(null);
        $queryProvider = $this->createMock(QueryProvider::class);
        $queryProvider->method('select')->willReturn($select);

        $mapper = new Mapper($schemaProvider, $queryProvider);

        $mapper->mapColumn($entitySchema, $columnSchema, 1);
    }
}
