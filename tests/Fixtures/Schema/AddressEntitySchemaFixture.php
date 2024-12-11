<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Schema;

use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Repository\UserRepositoryFixture;

class AddressEntitySchemaFixture
{
    public static function create(): EntitySchema
    {
        return new EntitySchema(
            entityClass: UserFixture::class,
            repositoryClass: UserRepositoryFixture::class,
            table: 'addresses',
            tableAlias: 'a',
            columns: [
                'id' => new ColumnSchema(
                    propertyName: 'id',
                    propertyType: PropertyTypeEnum::Int,
                    columnName: 'id',
                    columnType: 'int',
                    isPrimary: true,
                    isAutoIncrement: true,
                ),
                'street' => new ColumnSchema(
                    propertyName: 'firstName',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'street',
                    columnType: 'varchar',
                ),
                'city' => new ColumnSchema(
                    propertyName: 'lastName',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'city',
                    columnType: 'varchar',
                ),
                'country' => new ColumnSchema(
                    propertyName: 'lastName',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'country',
                    columnType: 'varchar',
                ),
            ],
        );
    }
}
