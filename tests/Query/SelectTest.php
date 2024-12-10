<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Query\Enum\DirectionEnum;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Query\Where\WhereBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
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
    /** @var Select<UserFixture> */
    private Select $select;

    protected function setUp(): void
    {
        $pdo = $this->createMock(PDO::class);
        $entityFactory = $this->createMock(EntityFactory::class);
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $schemaProvider->method('getEntitySchema')
            ->willReturn(EntitySchemaFixture::create());

        $this->select = new Select($pdo, $entityFactory, UserFixture::class, $schemaProvider);
    }

    /** @param array<string,scalar>|array{0: string, 1: string, 2: scalar}|list<array{0: string, 1: string, 2: scalar}> $where */
    #[TestWith([['id' => 1], 'id=?'])]
    #[TestWith([['id', '=', 1], 'id=?'])]
    #[TestWith([['id' => 1, 'first_name' => 'John'], 'id=? AND first_name=?'])]
    #[TestWith([[['id', '=', 1], ['first_name', '!=', 'John']], 'id=? AND first_name!=?'])]
    public function testWhere(array $where, string $expectedWhereSql): void
    {
        $select = $this->select;

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
        $select = $this->select;

        $select->orderBy($column, $direction);
        self::assertSame(
            'SELECT id,created_at,first_name,middle_name,last_name,email,is_active,type FROM users ORDER BY ' . $expectedOrderBySql,
            $select->getSql(),
        );
    }

    public function testColumns(): void
    {
        $select = $this->select;

        $select->columns(['id', 'first_name']);
        self::assertSame(
            'SELECT id,first_name FROM users',
            $select->getSql(),
        );
    }

    public function testLimit(): void
    {
        $select = $this->select;

        $select->limit(10);
        self::assertSame(
            'SELECT id,created_at,first_name,middle_name,last_name,email,is_active,type FROM users LIMIT 10',
            $select->getSql(),
        );
    }

    public function testOffset(): void
    {
        $select = $this->select;

        $select->offset(10);
        self::assertSame(
            'SELECT id,created_at,first_name,middle_name,last_name,email,is_active,type FROM users OFFSET 10',
            $select->getSql(),
        );
    }
}
