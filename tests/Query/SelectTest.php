<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use InvalidArgumentException;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Query\Enum\DirectionEnum;
use MarekSkopal\ORM\Query\Expression\RawExpression;
use MarekSkopal\ORM\Query\Model\Join;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Query\Where\WhereBuilder;
use MarekSkopal\ORM\Relation\RelationResolver;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AddressFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\CategoryFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\CountryFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\AddressEntitySchemaFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\CategoryEntitySchemaFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\CountryEntitySchemaFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\UserEntityWithAddressSchemaFixture;
use MarekSkopal\ORM\Utils\NameUtils;
use MarekSkopal\ORM\Utils\QuoteUtils;
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
#[UsesClass(QuoteUtils::class)]
#[UsesClass(RawExpression::class)]
final class SelectTest extends TestCase
{
    /** @var Select<UserWithAddressFixture> */
    private Select $select;

    private const string BaseSql = 'SELECT `u`.`id`,`u`.`created_at`,`u`.`first_name`,`u`.`middle_name`,`u`.`last_name`,`u`.`email`,`u`.`is_active`,`u`.`type`,`u`.`address_id`,`u`.`second_address_id` FROM `users` `u`';

    protected function setUp(): void
    {
        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($this::createStub(PDO::class));
        $database->method('getIdentifierQuoteChar')->willReturn('`');
        $relationResolver = $this::createStub(RelationResolver::class);
        $schemaProvider = $this::createStub(SchemaProvider::class);
        $schemaProvider->method('getEntitySchema')
            ->willReturnMap([
                [UserWithAddressFixture::class, UserEntityWithAddressSchemaFixture::create()],
                [AddressFixture::class, AddressEntitySchemaFixture::create()],
                [CountryFixture::class, CountryEntitySchemaFixture::create()],
            ]);

        $this->select = new Select(
            $database,
            UserWithAddressFixture::class,
            UserEntityWithAddressSchemaFixture::create(),
            $relationResolver,
            $schemaProvider,
        );
    }

    /** @param array<string,scalar>|array{0: string, 1: string, 2: scalar}|list<array{0: string, 1: string, 2: scalar}> $where */
    #[TestWith([['id' => 1], '`u`.`id`=?'])]
    #[TestWith([['id', '=', 1], '`u`.`id`=?'])]
    #[TestWith([['id' => 1, 'first_name' => 'John'], '`u`.`id`=? AND `u`.`first_name`=?'])]
    #[TestWith([[['id', '=', 1], ['first_name', '!=', 'John']], '`u`.`id`=? AND `u`.`first_name`!=?'])]
    #[TestWith(
        [[['address.country.name', 'LIKE', 'Czechia']], '`c`.`name` LIKE ?', ' LEFT JOIN `addresses` `a` ON `a`.`id`=`u`.`address_id` LEFT JOIN `countries` `c` ON `c`.`id`=`a`.`country_id`'],
    )]
    public function testWhere(array $where, string $expectedWhereSql, ?string $expectedJoinSql = null): void
    {
        $select = $this->select;

        $select->where($where);
        self::assertSame(
            self::BaseSql . $expectedJoinSql . ' WHERE ' . $expectedWhereSql,
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

    public function testParseColumnJoin(): void
    {
        $select = $this->select;

        $select->parseColumn('address.id');

        self::assertSame(
            self::BaseSql . ' LEFT JOIN `addresses` `a` ON `a`.`id`=`u`.`address_id`',
            $select->getSql(),
        );
    }

    public function testParseColumnJoinMultiple(): void
    {
        $select = $this->select;

        $select->parseColumn('address.country.id');

        self::assertSame(
            self::BaseSql . ' LEFT JOIN `addresses` `a` ON `a`.`id`=`u`.`address_id` LEFT JOIN `countries` `c` ON `c`.`id`=`a`.`country_id`',
            $select->getSql(),
        );
    }

    public function testOrWhere(): void
    {
        $select = $this->select;

        $select->where(['id' => 1])->orWhere(['first_name' => 'John']);

        self::assertSame(self::BaseSql . ' WHERE `u`.`id`=? OR `u`.`first_name`=?', $select->getSql());
        self::assertSame([1, 'John'], $select->getWhereBuilder()->getParams());
    }

    public function testWhereWithVoidClosure(): void
    {
        $select = $this->select;

        $select->where(['id' => 1])->where(static function (WhereBuilder $where): void {
            $where->where(['first_name' => 'John'])->orWhere(['last_name' => 'Doe']);
        });

        self::assertSame(
            self::BaseSql . ' WHERE `u`.`id`=? AND (`u`.`first_name`=? OR `u`.`last_name`=?)',
            $select->getSql(),
        );
        self::assertSame([1, 'John', 'Doe'], $select->getWhereBuilder()->getParams());
    }

    public function testCloneHasIndependentWhereConditions(): void
    {
        $select = $this->select->where(['id' => 1])->orWhere(['first_name' => 'John']);

        $clone = clone $select;
        $clone->where(['last_name' => 'Doe']);

        self::assertSame(self::BaseSql . ' WHERE `u`.`id`=? OR `u`.`first_name`=?', $select->getSql());
        self::assertSame([1, 'John'], $select->getWhereBuilder()->getParams());
        self::assertSame(
            self::BaseSql . ' WHERE `u`.`id`=? AND `u`.`last_name`=? OR `u`.`first_name`=?',
            $clone->getSql(),
        );
    }

    public function testCloneRebindsNestedConditionsToTheClone(): void
    {
        $select = $this->select->where(static function (WhereBuilder $where): void {
            $where->where(['address.city' => 'Brno'])->orWhere(['address.city' => 'Praha']);
        });

        $clone = clone $select;
        $clone->where(['id' => 1]);

        // Relation paths in nested conditions register joins on the builder being rendered.
        self::assertSame(
            self::BaseSql . ' LEFT JOIN `addresses` `a` ON `a`.`id`=`u`.`address_id` WHERE (`a`.`city`=? OR `a`.`city`=?) AND `u`.`id`=?',
            $clone->getSql(),
        );
        self::assertSame(
            self::BaseSql . ' LEFT JOIN `addresses` `a` ON `a`.`id`=`u`.`address_id` WHERE (`a`.`city`=? OR `a`.`city`=?)',
            $select->getSql(),
        );
    }

    public function testGetCountSqlIgnoresColumnsOrderLimitOffsetAndKeepsBuilderIntact(): void
    {
        $select = $this->select;

        $select
            ->columns(['id', 'first_name'])
            ->where(['is_active' => true])
            ->orderBy('id', DirectionEnum::Desc)
            ->limit(10)
            ->offset(5);

        $expectedSql = 'SELECT `u`.`id`,`u`.`first_name` FROM `users` `u` WHERE `u`.`is_active`=? ORDER BY `u`.`id` DESC LIMIT 10 OFFSET 5';

        self::assertSame($expectedSql, $select->getSql());
        self::assertSame('SELECT count(*) as c FROM `users` `u` WHERE `u`.`is_active`=?', $select->getCountSql());
        // Building the count SQL must not change the builder.
        self::assertSame($expectedSql, $select->getSql());
    }

    public function testGetCountSqlKeepsRelationJoins(): void
    {
        $select = $this->select;

        $select->where(['address.city' => 'Brno']);

        self::assertSame(
            'SELECT count(*) as c FROM `users` `u` LEFT JOIN `addresses` `a` ON `a`.`id`=`u`.`address_id` WHERE `a`.`city`=?',
            $select->getCountSql(),
        );
    }

    public function testParseColumnJoinTwoRelationsToSameTable(): void
    {
        $select = $this->select;

        self::assertSame('`a`.`city`', $select->parseColumn('address.city'));
        self::assertSame('`a_secondAddress`.`city`', $select->parseColumn('secondAddress.city'));
        // Repeated use of the same relation path reuses its alias and does not add a join.
        self::assertSame('`a_secondAddress`.`street`', $select->parseColumn('secondAddress.street'));

        self::assertSame(
            self::BaseSql
            . ' LEFT JOIN `addresses` `a` ON `a`.`id`=`u`.`address_id`'
            . ' LEFT JOIN `addresses` `a_secondAddress` ON `a_secondAddress`.`id`=`u`.`second_address_id`',
            $select->getSql(),
        );
    }

    public function testParseColumnJoinNestedRelationsToSameTable(): void
    {
        $select = $this->select;

        self::assertSame('`c`.`name`', $select->parseColumn('address.country.name'));
        self::assertSame('`c_secondAddress_country`.`name`', $select->parseColumn('secondAddress.country.name'));

        self::assertSame(
            self::BaseSql
            . ' LEFT JOIN `addresses` `a` ON `a`.`id`=`u`.`address_id`'
            . ' LEFT JOIN `countries` `c` ON `c`.`id`=`a`.`country_id`'
            . ' LEFT JOIN `addresses` `a_secondAddress` ON `a_secondAddress`.`id`=`u`.`second_address_id`'
            . ' LEFT JOIN `countries` `c_secondAddress_country` ON `c_secondAddress_country`.`id`=`a_secondAddress`.`country_id`',
            $select->getSql(),
        );
    }

    public function testParseColumnJoinSelfReference(): void
    {
        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($this::createStub(PDO::class));
        $database->method('getIdentifierQuoteChar')->willReturn('`');
        $schemaProvider = $this::createStub(SchemaProvider::class);
        $schemaProvider->method('getEntitySchema')->willReturn(CategoryEntitySchemaFixture::create());

        $select = new Select(
            $database,
            CategoryFixture::class,
            CategoryEntitySchemaFixture::create(),
            $this::createStub(RelationResolver::class),
            $schemaProvider,
        );

        self::assertSame('`c_parent`.`name`', $select->parseColumn('parent.name'));
        self::assertSame('`c_parent_parent`.`name`', $select->parseColumn('parent.parent.name'));

        self::assertSame(
            'SELECT `c`.`id`,`c`.`name`,`c`.`parent_id` FROM `categories` `c`'
            . ' LEFT JOIN `categories` `c_parent` ON `c_parent`.`id`=`c`.`parent_id`'
            . ' LEFT JOIN `categories` `c_parent_parent` ON `c_parent_parent`.`id`=`c_parent`.`parent_id`',
            $select->getSql(),
        );
    }

    public function testParseColumnAvoidsManualJoinAlias(): void
    {
        $select = $this->select;

        $select->join('address_id', 'addresses', 'a', 'id');

        self::assertSame('`a_secondAddress`.`city`', $select->parseColumn('secondAddress.city'));
    }

    #[TestWith(['id', '`u`.`id`'])]
    #[TestWith(['address.id', '`a`.`id`'])]
    #[TestWith(['firstName', '`u`.`first_name`'])]
    #[TestWith(['first_name', '`u`.`first_name`'])]
    #[TestWith(['address', '`u`.`address_id`'])]
    #[TestWith(['address_id', '`u`.`address_id`'])]
    #[TestWith(['unmapped_column', '`u`.`unmapped_column`'])]
    #[TestWith(['address.country', '`a`.`country_id`'])]
    #[TestWith(['address.country_id', '`a`.`country_id`'])]
    #[TestWith(['address_id.city', '`a`.`city`'])]
    #[TestWith(['address.country.name', '`c`.`name`'])]
    public function testParseColumn(string $column, string $expected): void
    {
        $select = $this->select;

        self::assertSame($expected, $select->parseColumn($column));
    }

    #[TestWith(['address.unknown'])]
    #[TestWith(['unknown.city'])]
    #[TestWith(['firstName.city'])]
    public function testParseColumnUnknownPathThrowsException(string $column): void
    {
        $select = $this->select;

        $this->expectException(InvalidArgumentException::class);
        $select->parseColumn($column);
    }

    public function testParseColumnRawExpression(): void
    {
        $select = $this->select;

        self::assertSame('count(*)', $select->parseColumn(new RawExpression('count(*)')));
    }

    #[TestWith(['count(*)'])]
    #[TestWith(['(SELECT password FROM users LIMIT 1)'])]
    #[TestWith(['id` , (SELECT 1) -- '])]
    #[TestWith(['id; DROP TABLE users'])]
    #[TestWith([''])]
    public function testParseColumnInvalidThrowsException(string $column): void
    {
        $select = $this->select;

        $this->expectException(InvalidArgumentException::class);
        $select->parseColumn($column);
    }

    public function testOrderByInvalidColumnThrowsException(): void
    {
        $select = $this->select;

        $this->expectException(InvalidArgumentException::class);
        $select->orderBy('name` DESC, (SELECT 1) -- ');
    }

    public function testColumnsRawExpression(): void
    {
        $select = $this->select;

        $select->columns([new RawExpression('count(*) as c'), 'id']);
        self::assertSame(
            'SELECT count(*) as c,`u`.`id` FROM `users` `u`',
            $select->getSql(),
        );
    }

    public function testGroupByRawExpression(): void
    {
        $select = $this->select;

        $select->groupBy([new RawExpression('lower(`u`.`email`)')]);
        self::assertSame(
            self::BaseSql . ' GROUP BY lower(`u`.`email`)',
            $select->getSql(),
        );
    }
}
