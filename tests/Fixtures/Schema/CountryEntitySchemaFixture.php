<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Schema;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\CountryFixture;
use MarekSkopal\ORM\Tests\Fixtures\Repository\CountryRepositoryFixture;

class CountryEntitySchemaFixture
{
    public static function create(): EntitySchema
    {
        return new EntitySchema(
            entityClass: CountryFixture::class,
            repositoryClass: CountryRepositoryFixture::class,
            table: 'countries',
            tableAlias: 'c',
            columns: [
                'id' => new ColumnSchema(
                    propertyName: 'id',
                    propertyType: PropertyTypeEnum::Int,
                    columnName: 'id',
                    columnType: Type::Int,
                    isPrimary: true,
                    isAutoIncrement: true,
                ),
                'name' => new ColumnSchema(
                    propertyName: 'name',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'name',
                    columnType: Type::String,
                ),
            ],
        );
    }
}
