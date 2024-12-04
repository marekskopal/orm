<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Schema\Builder;

use MarekSkopal\ORM\Schema\Builder\ColumnSchemaFactory;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Enum\CaseEnum;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AddressFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(ColumnSchemaFactory::class)]
class ColumnSchemaFactoryTest extends TestCase
{
    public function testCreateFromColumn(): void
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
