<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Schema;

use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Repository\UserRepositoryFixture;

class UserEntitySchemaFixture
{
    public static function create(): EntitySchema
    {
        return new EntitySchema(
            entityClass: UserFixture::class,
            repositoryClass: UserRepositoryFixture::class,
            table: 'users',
            tableAlias: 'u',
            columns: [
                'id' => new ColumnSchema(
                    propertyName: 'id',
                    propertyType: PropertyTypeEnum::Int,
                    columnName: 'id',
                    columnType: 'int',
                    isPrimary: true,
                ),
                'createdAt' => new ColumnSchema(
                    propertyName: 'createdAt',
                    propertyType: PropertyTypeEnum::DateTimeImmutable,
                    columnName: 'created_at',
                    columnType: 'datetime',
                ),
                'firstName' => new ColumnSchema(
                    propertyName: 'firstName',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'first_name',
                    columnType: 'varchar',
                ),
                'middleName' => new ColumnSchema(
                    propertyName: 'middleName',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'middle_name',
                    columnType: 'varchar',
                    isNullable: true,
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
                'type' => new ColumnSchema(
                    propertyName: 'type',
                    propertyType: PropertyTypeEnum::Enum,
                    columnName: 'type',
                    columnType: 'enum',
                    enumClass: UserTypeEnum::class,
                ),
            ],
        );
    }
}
