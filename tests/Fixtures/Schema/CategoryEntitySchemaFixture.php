<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Schema;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Repository\Repository;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\CategoryFixture;

class CategoryEntitySchemaFixture
{
    public static function create(): EntitySchema
    {
        return new EntitySchema(
            entityClass: CategoryFixture::class,
            repositoryClass: Repository::class,
            table: 'categories',
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
                'parent' => new ColumnSchema(
                    propertyName: 'parent',
                    propertyType: PropertyTypeEnum::Relation,
                    columnName: 'parent_id',
                    columnType: Type::Int,
                    relationType: RelationEnum::ManyToOne,
                    relationEntityClass: CategoryFixture::class,
                    isNullable: true,
                ),
            ],
        );
    }
}
