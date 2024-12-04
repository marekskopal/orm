<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Schema\Builder;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Schema\Builder\ColumnSchemaFactory;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Enum\CaseEnum;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AddressFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Code;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Utils\CaseUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(ColumnSchemaFactory::class)]
#[UsesClass(Column::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(PropertyTypeEnum::class)]
#[UsesClass(CaseUtils::class)]
#[UsesClass(ManyToOne::class)]
class ColumnSchemaFactoryTest extends TestCase
{
    public function testCreateFromColumnString(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory();

        $columnSchema = $columnSchemaFactory->create(
            new ReflectionProperty(
                UserFixture::class,
                'firstName',
            ),
            CaseEnum::SnakeCase,
        );

        $columnSchemaExpected = new ColumnSchema(
            propertyName: 'firstName',
            propertyType: PropertyTypeEnum::String,
            columnName: 'first_name',
            columnType: 'varchar(255)',
        );

        self::assertEquals($columnSchemaExpected, $columnSchema);
    }

    public function testCreateFromColumnInt(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory();

        $columnSchema = $columnSchemaFactory->create(
            new ReflectionProperty(
                UserFixture::class,
                'id',
            ),
            CaseEnum::SnakeCase,
        );

        $columnSchemaExpected = new ColumnSchema(
            propertyName: 'id',
            propertyType: PropertyTypeEnum::Int,
            columnName: 'id',
            columnType: 'int',
        );

        self::assertEquals($columnSchemaExpected, $columnSchema);
    }

    public function testCreateFromColumnUuid(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory();

        $columnSchema = $columnSchemaFactory->create(
            new ReflectionProperty(
                Code::class,
                'code',
            ),
            CaseEnum::SnakeCase,
        );

        $columnSchemaExpected = new ColumnSchema(
            propertyName: 'code',
            propertyType: PropertyTypeEnum::Uuid,
            columnName: 'code',
            columnType: 'uuid',
        );

        self::assertEquals($columnSchemaExpected, $columnSchema);
    }

    public function testCreateFromManyToOne(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory();

        $columnSchema = $columnSchemaFactory->create(
            new ReflectionProperty(
                UserWithAddressFixture::class,
                'address',
            ),
            CaseEnum::SnakeCase,
        );

        $columnSchemaExpected = new ColumnSchema(
            propertyName: 'address',
            propertyType: PropertyTypeEnum::Relation,
            columnName: 'address_id',
            columnType: 'int',
            relationType: RelationEnum::ManyToOne,
            relationEntityClass: AddressFixture::class,
        );

        self::assertEquals($columnSchemaExpected, $columnSchema);
    }
}
