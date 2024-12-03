<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Mapper;

use MarekSkopal\ORM\Factory\EntityFactory;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Mapper::class)]
final class MapperTest extends TestCase
{
    public function testMapColumnString(): void
    {
        $schema = new ColumnSchema('name', PropertyTypeEnum::String, 'name', 'varchar');
        $mapper = new Mapper($this->createMock(QueryProvider::class), $this->createMock(EntityFactory::class));
        $result = $mapper->mapColumn($schema, 'test');
        self::assertSame('test', $result);
    }

    public function testMapColumnInt(): void
    {
        $schema = new ColumnSchema('age', PropertyTypeEnum::Int, 'age', 'int');
        $mapper = new Mapper($this->createMock(QueryProvider::class), $this->createMock(EntityFactory::class));
        $result = $mapper->mapColumn($schema, 25);
        self::assertSame(25, $result);
    }

    public function testMapColumnFloat(): void
    {
        $schema = new ColumnSchema('price', PropertyTypeEnum::Float, 'price', 'float');
        $mapper = new Mapper($this->createMock(QueryProvider::class), $this->createMock(EntityFactory::class));
        $result = $mapper->mapColumn($schema, 19.99);
        self::assertSame(19.99, $result);
    }

    public function testMapColumnBool(): void
    {
        $schema = new ColumnSchema('isActive', PropertyTypeEnum::Bool, 'is_active', 'tinyint');
        $mapper = new Mapper($this->createMock(QueryProvider::class), $this->createMock(EntityFactory::class));
        $result = $mapper->mapColumn($schema, 1);
        self::assertTrue($result);
    }

    public function testMapColumnRelation(): void
    {
        $schema = new ColumnSchema('user', PropertyTypeEnum::Relation, 'user_id', 'int', RelationEnum::ManyToOne, UserFixture::class);

        $select = $this->createMock(Select::class);
        $select->method('where')->willReturnSelf();
        $select->method('fetch')->willReturn(['id' => 1]);
        $queryProvider = $this->createMock(QueryProvider::class);
        $queryProvider->method('select')->willReturn($select);
        $entityFactory = $this->createMock(EntityFactory::class);
        $entityFactory->method('create')->willReturn(UserFixture::create());

        $mapper = new Mapper($queryProvider, $entityFactory);

        $result = $mapper->mapColumn($schema, 1);
        self::assertInstanceOf(UserFixture::class, $result);
    }

    public function testMapColumnRelationNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Entity "MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture" with id "1" not found');

        $schema = new ColumnSchema('user', PropertyTypeEnum::Relation, 'user_id', 'int', RelationEnum::ManyToOne, UserFixture::class);

        $select = $this->createMock(Select::class);
        $select->method('where')->willReturnSelf();
        $select->method('fetch')->willReturn(null);
        $queryProvider = $this->createMock(QueryProvider::class);
        $queryProvider->method('select')->willReturn($select);
        $entityFactory = $this->createMock(EntityFactory::class);

        $mapper = new Mapper($queryProvider, $entityFactory);

        $mapper->mapColumn($schema, 1);
    }
}
