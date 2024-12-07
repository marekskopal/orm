<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Mapper;

use DateTime;
use DateTimeImmutable;
use Iterator;
use MarekSkopal\ORM\Mapper\ExtensionMapperProvider;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Extension\MapperExtension;
use MarekSkopal\ORM\Tests\Fixtures\Schema\EntitySchemaFixture;
use MarekSkopal\ORM\Utils\ValidationUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Lazy\LazyUuidFromString;
use Ramsey\Uuid\Uuid;

#[CoversClass(Mapper::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(ValidationUtils::class)]
#[UsesClass(ExtensionMapperProvider::class)]
final class MapperTest extends TestCase
{
    public function testMapColumnString(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('name', PropertyTypeEnum::String, 'name', 'varchar');
        $entitySchema = EntitySchemaFixture::create(columns: ['name' => $columnSchema]);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 'test');
        self::assertSame('test', $result);
    }

    public function testMapColumnInt(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('age', PropertyTypeEnum::Int, 'age', 'int');
        $entitySchema = EntitySchemaFixture::create(columns: ['age' => $columnSchema]);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 25);
        self::assertSame(25, $result);
    }

    public function testMapColumnFloat(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('price', PropertyTypeEnum::Float, 'price', 'float');
        $entitySchema = EntitySchemaFixture::create(columns: ['price' => $columnSchema]);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 19.99);
        self::assertSame(19.99, $result);
    }

    public function testMapColumnBool(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('isActive', PropertyTypeEnum::Bool, 'is_active', 'tinyint');
        $entitySchema = EntitySchemaFixture::create(columns: ['isActive' => $columnSchema]);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 1);
        self::assertTrue($result);
    }

    public function testMapColumnUuid(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('code', PropertyTypeEnum::Uuid, 'code', 'uuid');
        $entitySchema = EntitySchemaFixture::create(columns: ['code' => $columnSchema]);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 'f47ac10b-58cc-4372-a567-0e02b2c3d479');
        self::assertInstanceOf(LazyUuidFromString::class, $result);
        self::assertSame((string) Uuid::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'), (string) $result);
    }

    public function testMapColumnDatetimeFromTimestamp(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('createdAt', PropertyTypeEnum::DateTime, 'created_at', 'timestamp');
        $entitySchema = EntitySchemaFixture::create(columns: ['createdAt' => $columnSchema]);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 1704067200);
        self::assertInstanceOf(DateTime::class, $result);
        self::assertSame('2024-01-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testMapColumnDatetimeFromDatetime(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('createdAt', PropertyTypeEnum::DateTime, 'created_at', 'datetime');
        $entitySchema = EntitySchemaFixture::create(columns: ['createdAt' => $columnSchema]);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, '2024-01-01 00:00:00');
        self::assertInstanceOf(DateTime::class, $result);
        self::assertSame('2024-01-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testMapColumnDatetimeImmutableFromTimestamp(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('createdAt', PropertyTypeEnum::DateTimeImmutable, 'created_at', 'timestamp');
        $entitySchema = EntitySchemaFixture::create(columns: ['createdAt' => $columnSchema]);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 1704067200);
        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2024-01-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testMapColumnEnum(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('type', PropertyTypeEnum::Enum, 'type', 'enum', enumClass: UserTypeEnum::class);
        $entitySchema = EntitySchemaFixture::create(columns: ['type' => $columnSchema]);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 'admin');
        self::assertInstanceOf(UserTypeEnum::class, $result);
        self::assertSame(UserTypeEnum::Admin, $result);
    }

    public function testMapColumnDatetimeImmutableFromDatetime(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('createdAt', PropertyTypeEnum::DateTimeImmutable, 'created_at', 'timestamp');
        $entitySchema = EntitySchemaFixture::create(columns: ['createdAt' => $columnSchema]);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, '2024-01-01 00:00:00');
        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2024-01-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testMapColumnRelationManyToOne(): void
    {
        $primaryColumnSchema = new ColumnSchema('id', PropertyTypeEnum::Int, 'int', 'int', isPrimary: true);
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $schemaProvider->method('getPrimaryColumnSchema')->willReturn($primaryColumnSchema);
        $select = $this->createMock(Select::class);
        $select->method('where')->willReturnSelf();
        $select->method('fetchOne')->willReturn(UserFixture::create());
        $queryProvider = $this->createMock(QueryProvider::class);
        $queryProvider->method('select')->willReturn($select);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);

        $columnSchema = new ColumnSchema('user', PropertyTypeEnum::Relation, 'user_id', 'int', RelationEnum::ManyToOne, UserFixture::class);
        $entitySchema = EntitySchemaFixture::create(columns: ['user' => $columnSchema]);

        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 1);
        self::assertInstanceOf(UserFixture::class, $result);
    }

    public function testMapColumnRelationOneToMany(): void
    {
        $primaryColumnSchema = new ColumnSchema('id', PropertyTypeEnum::Int, 'int', 'int', isPrimary: true);
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $schemaProvider->method('getPrimaryColumnSchema')->willReturn($primaryColumnSchema);
        $select = $this->createMock(Select::class);
        $select->method('where')->willReturnSelf();
        $select->method('fetchOne')->willReturn(UserFixture::create());
        $queryProvider = $this->createMock(QueryProvider::class);
        $queryProvider->method('select')->willReturn($select);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);

        $columnSchema = new ColumnSchema('users', PropertyTypeEnum::Relation, 'users', 'int', RelationEnum::OneToMany, UserFixture::class);
        $entitySchema = EntitySchemaFixture::create(columns: ['users' => $columnSchema]);

        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 1);
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
        $select->method('fetchOne')->willReturn(null);
        $queryProvider = $this->createMock(QueryProvider::class);
        $queryProvider->method('select')->willReturn($select);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);

        $mapper->mapToProperty($entitySchema, $columnSchema, 1);
    }

    public function testMapColumnExtension(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);

        $columnSchema = new ColumnSchema('price', PropertyTypeEnum::Extension, 'price', 'decimal', extensionClass: MapperExtension::class);
        $entitySchema = EntitySchemaFixture::create(columns: ['price' => $columnSchema]);

        $mapper = new Mapper($schemaProvider);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 1.0);
        self::assertSame(2.0, $result);
    }
}
