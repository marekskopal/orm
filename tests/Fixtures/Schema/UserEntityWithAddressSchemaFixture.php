<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Schema;

use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AddressFixture;
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
            columns: [
                'id' => new ColumnSchema(
                    propertyName: 'id',
                    propertyType: PropertyTypeEnum::Int,
                    columnName: 'id',
                    columnType: 'int',
                    isPrimary: true,
                    isAutoIncrement: true,
                ),
                'firstName' => new ColumnSchema(
                    propertyName: 'firstName',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'first_name',
                    columnType: 'varchar',
                ),
                'lastName' => new ColumnSchema(
                    propertyName: 'lastName',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'last_name',
                    columnType: 'varchar',
                ),
                'email' => new ColumnSchema(
                    propertyName: 'email',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'email',
                    columnType: 'varchar',
                ),
                'isActive' => new ColumnSchema(
                    propertyName: 'isActive',
                    propertyType: PropertyTypeEnum::Bool,
                    columnName: 'is_active',
                    columnType: 'tinyint',
                ),
                'address' => new ColumnSchema(
                    propertyName: 'address',
                    propertyType: PropertyTypeEnum::Relation,
                    columnName: 'address_id',
                    columnType: 'int',
                    relationType: RelationEnum::ManyToOne,
                    relationEntityClass: AddressFixture::class,
                ),
            ],
        );
    }
}
