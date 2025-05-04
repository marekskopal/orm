<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Schema;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\CountryFixture;
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
                    columnType: Type::Int,
                    isPrimary: true,
                    isAutoIncrement: true,
                ),
                'street' => new ColumnSchema(
                    propertyName: 'street',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'street',
                    columnType: Type::String,
                ),
                'city' => new ColumnSchema(
                    propertyName: 'city',
                    propertyType: PropertyTypeEnum::String,
                    columnName: 'city',
                    columnType: Type::String,
                ),
                'country' => new ColumnSchema(
                    propertyName: 'country',
                    propertyType: PropertyTypeEnum::Relation,
                    columnName: 'country_id',
                    columnType: Type::Int,
                    relationType: RelationEnum::ManyToOne,
                    relationEntityClass: CountryFixture::class,
                ),
            ],
        );
    }
}
