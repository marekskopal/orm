<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Query\Model\Join;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Query\Where\WhereBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\AddressEntitySchemaFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\UserEntityWithAddressSchemaFixture;
use MarekSkopal\ORM\Utils\NameUtils;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WhereBuilder::class)]
#[UsesClass(Select::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(Join::class)]
#[UsesClass(NameUtils::class)]
final class WhereBuilderTest extends TestCase
{
    /** @var Select<UserFixture> */
    private Select $select;

    private WhereBuilder $whereBuilder;

    protected function setUp(): void
    {
        $pdo = $this::createStub(PDO::class);
        $entityFactory = $this::createStub(EntityFactory::class);
        $schemaProvider = $this::createStub(SchemaProvider::class);
        $schemaProvider->method('getEntitySchema')
            ->willReturn(
                UserEntityWithAddressSchemaFixture::create(),
                AddressEntitySchemaFixture::create(),
            );

        $this->select = new Select($pdo, UserFixture::class, UserEntityWithAddressSchemaFixture::create(), $entityFactory, $schemaProvider);

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
}
