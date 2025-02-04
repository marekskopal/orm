<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Mapper;

use DateTime;
use DateTimeImmutable;
use Iterator;
use MarekSkopal\ORM\Entity\EntityCache;
use MarekSkopal\ORM\Enum\Type;
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
    public function testMapToPropertyString(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);
        $entityCache = $this->createMock(EntityCache::class);

        $columnSchema = new ColumnSchema('name', PropertyTypeEnum::String, 'name', Type::String);
        $entitySchema = EntitySchemaFixture::create(columns: ['name' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 'test');
        self::assertSame('test', $result);
    }

    public function testMapToPropertyInt(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);
        $entityCache = $this->createMock(EntityCache::class);

        $columnSchema = new ColumnSchema('age', PropertyTypeEnum::Int, 'age', Type::Int);
        $entitySchema = EntitySchemaFixture::create(columns: ['age' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 25);
        self::assertSame(25, $result);
    }

    public function testMapToPropertyFloat(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);
        $entityCache = $this->createMock(EntityCache::class);

        $columnSchema = new ColumnSchema('price', PropertyTypeEnum::Float, 'price', Type::Float);
        $entitySchema = EntitySchemaFixture::create(columns: ['price' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 19.99);
        self::assertSame(19.99, $result);
    }

    public function testMapToPropertyBool(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);
        $entityCache = $this->createMock(EntityCache::class);

        $columnSchema = new ColumnSchema('isActive', PropertyTypeEnum::Bool, 'is_active', Type::Boolean);
        $entitySchema = EntitySchemaFixture::create(columns: ['isActive' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 1);
        self::assertTrue($result);
    }

    public function testMapToPropertyUuid(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);
        $entityCache = $this->createMock(EntityCache::class);

        $columnSchema = new ColumnSchema('code', PropertyTypeEnum::Uuid, 'code', Type::Uuid);
        $entitySchema = EntitySchemaFixture::create(columns: ['code' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 'f47ac10b-58cc-4372-a567-0e02b2c3d479');
        self::assertInstanceOf(LazyUuidFromString::class, $result);
        self::assertSame((string) Uuid::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'), (string) $result);
    }

    public function testMapToPropertyDatetimeFromTimestamp(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);
        $entityCache = $this->createMock(EntityCache::class);

        $columnSchema = new ColumnSchema('createdAt', PropertyTypeEnum::DateTime, 'created_at', Type::Timestamp);
        $entitySchema = EntitySchemaFixture::create(columns: ['createdAt' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 1704067200);
        self::assertInstanceOf(DateTime::class, $result);
        self::assertSame('2024-01-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testMapToPropertyDatetimeFromDatetime(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);
        $entityCache = $this->createMock(EntityCache::class);

        $columnSchema = new ColumnSchema('createdAt', PropertyTypeEnum::DateTime, 'created_at', Type::DateTime);
        $entitySchema = EntitySchemaFixture::create(columns: ['createdAt' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, '2024-01-01 00:00:00');
        self::assertInstanceOf(DateTime::class, $result);
        self::assertSame('2024-01-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testMapToPropertyDatetimeImmutableFromTimestamp(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);
        $entityCache = $this->createMock(EntityCache::class);

        $columnSchema = new ColumnSchema('createdAt', PropertyTypeEnum::DateTimeImmutable, 'created_at', Type::Timestamp);
        $entitySchema = EntitySchemaFixture::create(columns: ['createdAt' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 1704067200);
        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2024-01-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testMapToPropertyEnum(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);
        $entityCache = $this->createMock(EntityCache::class);

        $columnSchema = new ColumnSchema('type', PropertyTypeEnum::Enum, 'type', Type::Enum, enumClass: UserTypeEnum::class);
        $entitySchema = EntitySchemaFixture::create(columns: ['type' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 'admin');
        self::assertInstanceOf(UserTypeEnum::class, $result);
        self::assertSame(UserTypeEnum::Admin, $result);
    }

    public function testMapToPropertyDatetimeImmutableFromDatetime(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);
        $entityCache = $this->createMock(EntityCache::class);

        $columnSchema = new ColumnSchema('createdAt', PropertyTypeEnum::DateTimeImmutable, 'created_at', Type::Timestamp);
        $entitySchema = EntitySchemaFixture::create(columns: ['createdAt' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, '2024-01-01 00:00:00');
        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2024-01-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testMapToPropertyRelationManyToOne(): void
    {
        $primaryColumnSchema = new ColumnSchema('id', PropertyTypeEnum::Int, 'int', Type::Int, isPrimary: true);
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $schemaProvider->method('getPrimaryColumnSchema')->willReturn($primaryColumnSchema);
        $select = $this->createMock(Select::class);
        $select->method('where')->willReturnSelf();
        $select->method('fetchOne')->willReturn(UserFixture::create());
        $queryProvider = $this->createMock(QueryProvider::class);
        $queryProvider->method('select')->willReturn($select);
        $entityCache = $this->createMock(EntityCache::class);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);

        $columnSchema = new ColumnSchema('user', PropertyTypeEnum::Relation, 'user_id', Type::Int, RelationEnum::ManyToOne, UserFixture::class);
        $entitySchema = EntitySchemaFixture::create(columns: ['user' => $columnSchema]);

        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 1);
        self::assertInstanceOf(UserFixture::class, $result);
    }

    public function testMapToPropertyRelationOneToMany(): void
    {
        $primaryColumnSchema = new ColumnSchema('id', PropertyTypeEnum::Int, 'int', Type::Int, isPrimary: true);
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $schemaProvider->method('getPrimaryColumnSchema')->willReturn($primaryColumnSchema);
        $select = $this->createMock(Select::class);
        $select->method('where')->willReturnSelf();
        $select->method('fetchOne')->willReturn(UserFixture::create());
        $queryProvider = $this->createMock(QueryProvider::class);
        $queryProvider->method('select')->willReturn($select);
        $entityCache = $this->createMock(EntityCache::class);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);

        $columnSchema = new ColumnSchema(
            'users',
            PropertyTypeEnum::Relation,
            'users',
            Type::Int,
            RelationEnum::OneToMany,
            UserFixture::class,
            'address_id',
        );
        $entitySchema = EntitySchemaFixture::create(columns: ['users' => $columnSchema]);

        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 1);
        self::assertInstanceOf(Iterator::class, $result);
    }

    public function testMapToPropertyRelationNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Entity "MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture" with id "1" not found');

        $columnSchema = new ColumnSchema('user', PropertyTypeEnum::Relation, 'user_id', Type::Int, RelationEnum::ManyToOne, UserFixture::class);
        $entitySchema = EntitySchemaFixture::create(columns: ['user' => $columnSchema]);

        $primaryColumnSchema = new ColumnSchema('id', PropertyTypeEnum::Int, 'int', Type::Int, isPrimary: true);
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $schemaProvider->method('getPrimaryColumnSchema')->willReturn($primaryColumnSchema);
        $select = $this->createMock(Select::class);
        $select->method('where')->willReturnSelf();
        $select->method('fetchOne')->willReturn(null);
        $queryProvider = $this->createMock(QueryProvider::class);
        $queryProvider->method('select')->willReturn($select);
        $entityCache = $this->createMock(EntityCache::class);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);

        $property = $mapper->mapToProperty($entitySchema, $columnSchema, 1);
        // @phpstan-ignore-next-line property.nonObject expr.resultUnused
        $property->id;
    }

    public function testMapToPropertyExtension(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);
        $entityCache = $this->createMock(EntityCache::class);

        $columnSchema = new ColumnSchema('price', PropertyTypeEnum::Extension, 'price', Type::Decimal, extensionClass: MapperExtension::class);
        $entitySchema = EntitySchemaFixture::create(columns: ['price' => $columnSchema]);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToProperty($entitySchema, $columnSchema, 1.0);
        self::assertSame(2.0, $result);
    }

    public function testMapToColumnTimestamp(): void
    {
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $queryProvider = $this->createMock(QueryProvider::class);
        $entityCache = $this->createMock(EntityCache::class);

        $columnSchema = new ColumnSchema('createdAt', PropertyTypeEnum::DateTimeImmutable, 'created_at', Type::Timestamp);

        $mapper = new Mapper($schemaProvider, $entityCache);
        $mapper->setQueryProvider($queryProvider);
        $result = $mapper->mapToColumn($columnSchema, new DateTimeImmutable('2024-01-01 00:00:00'));
        self::assertSame('2024-01-01 00:00:00', $result);
    }
}
