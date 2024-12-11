<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Query\Enum\DirectionEnum;
use MarekSkopal\ORM\Query\Model\Join;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Query\Where\WhereBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\AddressEntitySchemaFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\UserEntityWithAddressSchemaFixture;
use MarekSkopal\ORM\Utils\NameUtils;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Select::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(WhereBuilder::class)]
#[UsesClass(Join::class)]
#[UsesClass(NameUtils::class)]
final class SelectTest extends TestCase
{
    /** @var Select<UserFixture> */
    private Select $select;

    private const string BaseSql = 'SELECT `u`.`id`,`u`.`created_at`,`u`.`first_name`,`u`.`middle_name`,`u`.`last_name`,`u`.`email`,`u`.`is_active`,`u`.`type`,`u`.`address_id`,`u`.`second_address_id` FROM `users` `u`';

    protected function setUp(): void
    {
        $pdo = $this->createMock(PDO::class);
        $entityFactory = $this->createMock(EntityFactory::class);
        $schemaProvider = $this->createMock(SchemaProvider::class);
        $schemaProvider->method('getEntitySchema')
            ->willReturn(
                UserEntityWithAddressSchemaFixture::create(),
                UserEntityWithAddressSchemaFixture::create(),
                AddressEntitySchemaFixture::create(),
            );

        $this->select = new Select($pdo, $entityFactory, UserWithAddressFixture::class, $schemaProvider);
    }

    /** @param array<string,scalar>|array{0: string, 1: string, 2: scalar}|list<array{0: string, 1: string, 2: scalar}> $where */
    #[TestWith([['id' => 1], '`u`.`id`=?'])]
    #[TestWith([['id', '=', 1], '`u`.`id`=?'])]
    #[TestWith([['id' => 1, 'first_name' => 'John'], '`u`.`id`=? AND `u`.`first_name`=?'])]
    #[TestWith([[['id', '=', 1], ['first_name', '!=', 'John']], '`u`.`id`=? AND `u`.`first_name`!=?'])]
    public function testWhere(array $where, string $expectedWhereSql): void
    {
        $select = $this->select;

        $select->where($where);
        self::assertSame(
            self::BaseSql . ' WHERE ' . $expectedWhereSql,
            $select->getSql(),
        );
    }

    #[TestWith(['id', 'ASC', '`u`.`id` ASC'])]
    #[TestWith(['id', 'DESC', '`u`.`id` DESC'])]
    #[TestWith(['id', DirectionEnum::Asc, '`u`.`id` ASC'])]
    #[TestWith(['id', DirectionEnum::Desc, '`u`.`id` DESC'])]
    public function testOrderBy(string $column, DirectionEnum|string $direction, string $expectedOrderBySql): void
    {
        $select = $this->select;

        $select->orderBy($column, $direction);
        self::assertSame(
            self::BaseSql . ' ORDER BY ' . $expectedOrderBySql,
            $select->getSql(),
        );
    }

    #[TestWith(['address.street', DirectionEnum::Asc, '`a`.`street` ASC', '`addresses` `a` ON `a`.`id`=`u`.`address_id`'])]
    public function testOrderByRelation(
        string $column,
        DirectionEnum|string $direction,
        string $expectedOrderBySql,
        string $expectedJoinSql,
    ): void
    {
        $select = $this->select;

        $select->orderBy($column, $direction);
        self::assertSame(
            self::BaseSql . ' LEFT JOIN ' . $expectedJoinSql . ' ORDER BY ' . $expectedOrderBySql,
            $select->getSql(),
        );
    }

    public function testColumns(): void
    {
        $select = $this->select;

        $select->columns(['id', 'first_name']);
        self::assertSame(
            'SELECT `u`.`id`,`u`.`first_name` FROM `users` `u`',
            $select->getSql(),
        );
    }

    public function testLimit(): void
    {
        $select = $this->select;

        $select->limit(10);
        self::assertSame(
            self::BaseSql . ' LIMIT 10',
            $select->getSql(),
        );
    }

    public function testOffset(): void
    {
        $select = $this->select;

        $select->offset(10);
        self::assertSame(
            self::BaseSql . ' OFFSET 10',
            $select->getSql(),
        );
    }

    public function testParseColumns(): void
    {
        $select = $this->select;

        $select->parseColumn('address.id');

        self::assertSame(
            self::BaseSql . ' LEFT JOIN `addresses` `a` ON `a`.`id`=`u`.`address_id`',
            $select->getSql(),
        );
    }
}
