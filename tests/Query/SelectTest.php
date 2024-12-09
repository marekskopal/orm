<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Query\Enum\DirectionEnum;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Query\WhereBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\EntitySchemaFixture;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Select::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(WhereBuilder::class)]
final class SelectTest extends TestCase
{
    /** @param array<string,scalar>|array{0: string, 1: string, 2: scalar}|list<array{0: string, 1: string, 2: scalar}> $where */
    #[TestWith([['id' => 1], 'id=?'])]
    #[TestWith([['id', '=', 1], 'id=?'])]
    #[TestWith([['id' => 1, 'first_name' => 'John'], 'id=? AND first_name=?'])]
    #[TestWith([[['id', '=', 1], ['first_name', '!=', 'John']], 'id=? AND first_name!=?'])]
    public function testWhere(array $where, string $expectedWhereSql): void
    {
        $pdo = $this->createMock(PDO::class);
        $entityFactory = $this->createMock(EntityFactory::class);
        $entitySchema = EntitySchemaFixture::create();

        $select = new Select($pdo, $entityFactory, UserFixture::class, $entitySchema);

        $select->where($where);
        self::assertSame(
            'SELECT id,created_at,first_name,middle_name,last_name,email,is_active,type FROM users WHERE ' . $expectedWhereSql,
            $select->getSql(),
        );
    }

    #[TestWith(['id', 'ASC', 'id ASC'])]
    #[TestWith(['id', 'DESC', 'id DESC'])]
    #[TestWith(['id', DirectionEnum::Asc, 'id ASC'])]
    #[TestWith(['id', DirectionEnum::Desc, 'id DESC'])]
    public function testOrderBy(string $column, DirectionEnum|string $direction, string $expectedOrderBySql): void
    {
        $pdo = $this->createMock(PDO::class);
        $entityFactory = $this->createMock(EntityFactory::class);
        $entitySchema = EntitySchemaFixture::create();

        $select = new Select($pdo, $entityFactory, UserFixture::class, $entitySchema);

        $select->orderBy($column, $direction);
        self::assertSame(
            'SELECT id,created_at,first_name,middle_name,last_name,email,is_active,type FROM users ORDER BY ' . $expectedOrderBySql,
            $select->getSql(),
        );
    }

    public function testColumns(): void
    {
        $pdo = $this->createMock(PDO::class);
        $entityFactory = $this->createMock(EntityFactory::class);
        $entitySchema = EntitySchemaFixture::create();

        $select = new Select($pdo, $entityFactory, UserFixture::class, $entitySchema);

        $select->columns(['id', 'first_name']);
        self::assertSame(
            'SELECT id,first_name FROM users',
            $select->getSql(),
        );
    }

    public function testLimit(): void
    {
        $pdo = $this->createMock(PDO::class);
        $entityFactory = $this->createMock(EntityFactory::class);
        $entitySchema = EntitySchemaFixture::create();

        $select = new Select($pdo, $entityFactory, UserFixture::class, $entitySchema);

        $select->limit(10);
        self::assertSame(
            'SELECT id,created_at,first_name,middle_name,last_name,email,is_active,type FROM users LIMIT 10',
            $select->getSql(),
        );
    }

    public function testOffset(): void
    {
        $pdo = $this->createMock(PDO::class);
        $entityFactory = $this->createMock(EntityFactory::class);
        $entitySchema = EntitySchemaFixture::create();

        $select = new Select($pdo, $entityFactory, UserFixture::class, $entitySchema);

        $select->offset(10);
        self::assertSame(
            'SELECT id,created_at,first_name,middle_name,last_name,email,is_active,type FROM users OFFSET 10',
            $select->getSql(),
        );
    }
}
