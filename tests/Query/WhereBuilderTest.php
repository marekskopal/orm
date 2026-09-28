<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use InvalidArgumentException;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Query\Expression\RawExpression;
use MarekSkopal\ORM\Query\Model\Join;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Query\Where\WhereBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AddressFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\AddressEntitySchemaFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\UserEntityWithAddressSchemaFixture;
use MarekSkopal\ORM\Utils\NameUtils;
use MarekSkopal\ORM\Utils\QuoteUtils;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WhereBuilder::class)]
#[UsesClass(Select::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(Join::class)]
#[UsesClass(NameUtils::class)]
#[UsesClass(QuoteUtils::class)]
#[UsesClass(RawExpression::class)]
final class WhereBuilderTest extends TestCase
{
    /** @var Select<UserFixture> */
    private Select $select;

    private WhereBuilder $whereBuilder;

    protected function setUp(): void
    {
        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($this::createStub(PDO::class));
        $database->method('getIdentifierQuoteChar')->willReturn('`');
        $entityFactory = $this::createStub(EntityFactory::class);
        $schemaProvider = $this::createStub(SchemaProvider::class);
        $schemaProvider->method('getEntitySchema')
            ->willReturnMap([
                [UserFixture::class, UserEntityWithAddressSchemaFixture::create()],
                [AddressFixture::class, AddressEntitySchemaFixture::create()],
            ]);

        $this->select = new Select(
            $database,
            UserFixture::class,
            UserEntityWithAddressSchemaFixture::create(),
            $entityFactory,
            $schemaProvider,
        );

        $this->whereBuilder = new WhereBuilder($this->select);
    }

    public function testBuild(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        self::assertSame(
            '`u`.`id`=? AND `u`.`first_name`=? AND `u`.`last_name`=?',
            $whereBuilder->build(),
        );
    }

    public function testGetParams(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        self::assertSame(
            [1, 'John', 'Doe'],
            $whereBuilder->getParams(),
        );
    }

    public function testBuildOr(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'id' => 1,
        ]);
        $whereBuilder->orWhere([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        self::assertSame(
            '`u`.`id`=? OR `u`.`first_name`=? AND `u`.`last_name`=?',
            $whereBuilder->build(),
        );
    }

    public function testBuildOrOr(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'id' => 1,
        ]);
        $whereBuilder->orWhere(
            ['first_name' => 'John'],
        );
        $whereBuilder->orWhere(
            ['last_name' => 'Doe'],
        );

        self::assertSame(
            '`u`.`id`=? OR `u`.`first_name`=? OR `u`.`last_name`=?',
            $whereBuilder->build(),
        );
    }

    public function testBuildOrWithoutWhere(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->orWhere(['first_name' => 'John']);
        $whereBuilder->orWhere(['last_name' => 'Doe']);

        self::assertSame(
            '`u`.`first_name`=? OR `u`.`last_name`=?',
            $whereBuilder->build(),
        );
    }

    public function testBuildSubOrWithoutWhere(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where(fn(WhereBuilder $builder) => $builder->orWhere(['first_name' => 'John']));

        self::assertSame(
            '(`u`.`first_name`=?)',
            $whereBuilder->build(),
        );
    }

    public function testBuildEmptyOrWhereIsIgnored(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where(['id' => 1]);
        $whereBuilder->orWhere([]);

        self::assertSame(
            '`u`.`id`=?',
            $whereBuilder->build(),
        );
    }

    public function testBuildSub(): void
    {
        // id=1 AND ((first_name='John' AND last_name='Doe') OR (first_name='Jane' AND last_name='Doe'))

        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'id' => 1,
        ]);
        $whereBuilder->where(fn(WhereBuilder $builder) => $builder
            ->where([
                'first_name' => 'John',
                'last_name' => 'Doe',
            ])
            ->orWhere([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
            ]),);

        self::assertSame(
            '`u`.`id`=? AND (`u`.`first_name`=? AND `u`.`last_name`=? OR `u`.`first_name`=? AND `u`.`last_name`=?)',
            $whereBuilder->build(),
        );
    }

    public function testGetParamsSub(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'id' => 1,
        ]);
        $whereBuilder->where(fn(WhereBuilder $builder) => $builder
            ->where([
                'first_name' => 'John',
                'last_name' => 'Doe',
            ])
            ->orWhere([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
            ]),);

        self::assertSame(
            [1, 'John', 'Doe', 'Jane', 'Doe'],
            $whereBuilder->getParams(),
        );
    }

    public function testBuildIsNull(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where(['middle_name' => null]);

        self::assertSame('`u`.`middle_name` IS NULL', $whereBuilder->build());
        self::assertSame([], $whereBuilder->getParams());
    }

    #[TestWith(['!='])]
    #[TestWith(['<>'])]
    public function testBuildIsNotNull(string $operator): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where(['middle_name', $operator, null]);

        self::assertSame('`u`.`middle_name` IS NOT NULL', $whereBuilder->build());
        self::assertSame([], $whereBuilder->getParams());
    }

    public function testBuildNullSkipsParamButKeepsOrder(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'id' => 1,
            'middle_name' => null,
            'first_name' => 'John',
        ]);

        self::assertSame('`u`.`id`=? AND `u`.`middle_name` IS NULL AND `u`.`first_name`=?', $whereBuilder->build());
        self::assertSame([1, 'John'], $whereBuilder->getParams());
    }

    public function testBuildNullWithUnsupportedOperatorThrowsException(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where(['id', '<', null]);

        $this->expectException(InvalidArgumentException::class);
        $whereBuilder->build();
    }

    /** @param list<int> $expectedParams */
    #[TestWith([true, [1]])]
    #[TestWith([false, [0]])]
    public function testGetParamsBool(bool $value, array $expectedParams): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where(['is_active' => $value]);

        self::assertSame('`u`.`is_active`=?', $whereBuilder->build());
        self::assertSame($expectedParams, $whereBuilder->getParams());
    }

    public function testGetParamsBoolInArray(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where(['is_active', 'IN', [true, false]]);

        self::assertSame([1, 0], $whereBuilder->getParams());
    }

    public function testBuildIn(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'id',
            'IN',
            [1, 2, 3],
        ]);

        self::assertSame(
            '`u`.`id` IN (?,?,?)',
            $whereBuilder->build(),
        );
    }

    public function testGetParamsIn(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'id',
            'IN',
            [1, 2, 3],
        ]);

        self::assertSame(
            [1, 2, 3],
            $whereBuilder->getParams(),
        );
    }

    public function testBuildInSelect(): void
    {
        $whereBuilder = $this->whereBuilder;

        $select = $this->select;
        $select->columns(['id'])
            ->where([
                'first_name' => 'John',
            ]);

        $whereBuilder->where([
            'id',
            'IN',
            $select,
        ]);

        self::assertSame(
            '`u`.`id` IN (' . $select->getSql() . ')',
            $whereBuilder->build(),
        );
    }

    public function testBuildRelation(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'address.id' => 1,
        ]);

        self::assertSame(
            '`a`.`id`=?',
            $whereBuilder->build(),
        );
    }

    public function testBuildLike(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'first_name',
            'LIKE',
            '%John%',
        ]);

        self::assertSame(
            '`u`.`first_name` LIKE ?',
            $whereBuilder->build(),
        );
    }

    public function testBuildNotLike(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'first_name',
            'not like',
            '%John%',
        ]);

        self::assertSame(
            '`u`.`first_name` NOT LIKE ?',
            $whereBuilder->build(),
        );
    }

    public function testBuildNotIn(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'id',
            'NOT IN',
            [1, 2, 3],
        ]);

        self::assertSame(
            '`u`.`id` NOT IN (?,?,?)',
            $whereBuilder->build(),
        );
    }

    #[TestWith(['='])]
    #[TestWith(['!='])]
    #[TestWith(['<>'])]
    #[TestWith(['<'])]
    #[TestWith(['<='])]
    #[TestWith(['>'])]
    #[TestWith(['>='])]
    public function testBuildAllowedOperator(string $operator): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'id',
            $operator,
            1,
        ]);

        self::assertSame(
            '`u`.`id`' . $operator . '?',
            $whereBuilder->build(),
        );
    }

    public function testBuildRawExpressionColumn(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            new RawExpression('lower(`u`.`email`)'),
            '=',
            'john@example.com',
        ]);

        self::assertSame(
            'lower(`u`.`email`)=?',
            $whereBuilder->build(),
        );
    }

    public function testBuildInvalidColumnThrowsException(): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'id` = 1 OR (SELECT 1) -- ',
            '=',
            1,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $whereBuilder->build();
    }

    #[TestWith(['= 1 OR 1=1 -- '])]
    #[TestWith(['= (SELECT password FROM users LIMIT 1) OR id ='])]
    #[TestWith(['BETWEEN'])]
    #[TestWith([''])]
    public function testBuildNotAllowedOperatorThrowsException(string $operator): void
    {
        $whereBuilder = $this->whereBuilder;

        $whereBuilder->where([
            'id',
            $operator,
            1,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $whereBuilder->build();
    }
}
