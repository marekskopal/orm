<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Schema\Builder;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Schema\Builder\ColumnSchemaFactory;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Enum\CaseEnum;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Address;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AddressWithUsersFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Code;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

#[CoversClass(ColumnSchemaFactory::class)]
#[UsesClass(Column::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(PropertyTypeEnum::class)]
#[UsesClass(CaseUtils::class)]
#[UsesClass(ManyToOne::class)]
#[UsesClass(OneToMany::class)]
#[UsesClass(ColumnEnum::class)]
#[UsesClass(NameUtils::class)]
class ColumnSchemaFactoryTest extends TestCase
{
    public function testCreateFromColumnString(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory(new ReflectionClass(UserFixture::class));

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

    public function testCreateFromColumnStringNullable(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory(new ReflectionClass(UserFixture::class));

        $columnSchema = $columnSchemaFactory->create(
            new ReflectionProperty(
                UserFixture::class,
                'middleName',
            ),
            CaseEnum::SnakeCase,
        );

        $columnSchemaExpected = new ColumnSchema(
            propertyName: 'middleName',
            propertyType: PropertyTypeEnum::String,
            columnName: 'middle_name',
            columnType: 'varchar(255)',
            isNullable: true,
        );

        self::assertEquals($columnSchemaExpected, $columnSchema);
    }

    public function testCreateFromColumnInt(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory(new ReflectionClass(UserFixture::class));

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
            isPrimary: true,
        );

        self::assertEquals($columnSchemaExpected, $columnSchema);
    }

    public function testCreateFromColumnUuid(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory(new ReflectionClass(Code::class));

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

    public function testCreateFromColumnDatetimeImmutable(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory(new ReflectionClass(UserFixture::class));

        $columnSchema = $columnSchemaFactory->create(
            new ReflectionProperty(
                UserFixture::class,
                'createdAt',
            ),
            CaseEnum::SnakeCase,
        );

        $columnSchemaExpected = new ColumnSchema(
            propertyName: 'createdAt',
            propertyType: PropertyTypeEnum::DateTimeImmutable,
            columnName: 'created_at',
            columnType: 'timestamp',
        );

        self::assertEquals($columnSchemaExpected, $columnSchema);
    }

    public function testCreateFromColumnEnum(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory(new ReflectionClass(UserFixture::class));

        $columnSchema = $columnSchemaFactory->create(
            new ReflectionProperty(
                UserFixture::class,
                'type',
            ),
            CaseEnum::SnakeCase,
        );

        $columnSchemaExpected = new ColumnSchema(
            propertyName: 'type',
            propertyType: PropertyTypeEnum::Enum,
            columnName: 'type',
            columnType: 'enum',
            enumClass: UserTypeEnum::class,
        );

        self::assertEquals($columnSchemaExpected, $columnSchema);
    }

    public function testCreateFromManyToOne(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory(new ReflectionClass(UserWithAddressFixture::class));

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
            relationEntityClass: AddressWithUsersFixture::class,
        );

        self::assertEquals($columnSchemaExpected, $columnSchema);
    }

    public function testCreateFromManyToOneNullable(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory(new ReflectionClass(UserWithAddressFixture::class));

        $columnSchema = $columnSchemaFactory->create(
            new ReflectionProperty(
                UserWithAddressFixture::class,
                'secondAddress',
            ),
            CaseEnum::SnakeCase,
        );

        $columnSchemaExpected = new ColumnSchema(
            propertyName: 'secondAddress',
            propertyType: PropertyTypeEnum::Relation,
            columnName: 'second_address_id',
            columnType: 'int',
            relationType: RelationEnum::ManyToOne,
            relationEntityClass: AddressWithUsersFixture::class,
            isNullable: true,
        );

        self::assertEquals($columnSchemaExpected, $columnSchema);
    }

    public function testCreateFromOneToMany(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory(new ReflectionClass(AddressWithUsersFixture::class));

        $columnSchema = $columnSchemaFactory->create(
            new ReflectionProperty(
                AddressWithUsersFixture::class,
                'users',
            ),
            CaseEnum::SnakeCase,
        );

        $columnSchemaExpected = new ColumnSchema(
            propertyName: 'users',
            propertyType: PropertyTypeEnum::Relation,
            columnName: 'users',
            columnType: 'int',
            relationType: RelationEnum::OneToMany,
            relationEntityClass: UserWithAddressFixture::class,
            relationColumnName: 'address_id',
        );

        self::assertEquals($columnSchemaExpected, $columnSchema);
    }

    public function testCreateFromOneToManyOverrided(): void
    {
        $columnSchemaFactory = new ColumnSchemaFactory(new ReflectionClass(Address::class));

        $columnSchema = $columnSchemaFactory->create(
            new ReflectionProperty(
                Address::class,
                'users',
            ),
            CaseEnum::SnakeCase,
        );

        $columnSchemaExpected = new ColumnSchema(
            propertyName: 'users',
            propertyType: PropertyTypeEnum::Relation,
            columnName: 'users',
            columnType: 'int',
            relationType: RelationEnum::OneToMany,
            relationEntityClass: UserFixture::class,
            relationColumnName: 'address_id',
        );

        self::assertEquals($columnSchemaExpected, $columnSchema);
    }
}
