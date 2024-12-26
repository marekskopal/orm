<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Schema;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AddressFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Tests\Fixtures\Repository\UserRepositoryWithAddressFixture;

class UserEntityWithAddressSchemaFixture
{
    public static function create(): EntitySchema
    {
        return new EntitySchema(
            entityClass: UserWithAddressFixture::class,
            repositoryClass: UserRepositoryWithAddressFixture::class,
            table: 'users',
            tableAlias: 'u',
            columns: [
                'id' => new ColumnSchema(
                    propertyName: 'id',
                    propertyType: PropertyTypeEnum::Int,
                    columnName: 'id',
                    columnType: Type::Int,
                    isPrimary: true,
                ),
                'createdAt' => new ColumnSchema(
                    propertyName: 'createdAt',
                    propertyType: PropertyTypeEnum::DateTimeImmutable,
                    columnName: 'created_at',
                    columnType: Type::DateTime,
                ),
                'firstName' => new ColumnSchema(
                    propertyName: 'firstName',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'first_name',
                    columnType: Type::String,
                ),
                'middleName' => new ColumnSchema(
                    propertyName: 'middleName',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'middle_name',
                    columnType: Type::String,
                    isNullable: true,
                ),
                'lastName' => new ColumnSchema(
                    propertyName: 'lastName',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'last_name',
                    columnType: Type::String,
                ),
                'email' => new ColumnSchema(
                    propertyName: 'email',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'email',
                    columnType: Type::String,
                ),
                'isActive' => new ColumnSchema(
                    propertyName: 'isActive',
                    propertyType: PropertyTypeEnum::Bool,
                    columnName: 'is_active',
                    columnType: Type::Boolean,
                ),
                'type' => new ColumnSchema(
                    propertyName: 'type',
                    propertyType: PropertyTypeEnum::Enum,
                    columnName: 'type',
                    columnType: Type::Enum,
                    enumClass: UserTypeEnum::class,
                ),
                'address' => new ColumnSchema(
                    propertyName: 'address',
                    propertyType: PropertyTypeEnum::Relation,
                    columnName: 'address_id',
                    columnType: Type::Int,
                    relationType: RelationEnum::ManyToOne,
                    relationEntityClass: AddressFixture::class,
                ),
                'secondAddress' => new ColumnSchema(
                    propertyName: 'address',
                    propertyType: PropertyTypeEnum::Relation,
                    columnName: 'second_address_id',
                    columnType: Type::Int,
                    relationType: RelationEnum::ManyToOne,
                    relationEntityClass: AddressFixture::class,
                    isNullable: true,
                ),
            ],
        );
    }
}
