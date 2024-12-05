<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Schema;

use MarekSkopal\ORM\Repository\RepositoryInterface;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Repository\UserRepositoryFixture;

class EntitySchemaFixture
{
    /**
     * @param class-string $entityClass
     * @param class-string<RepositoryInterface<object>> $repositoryClass
     * @param array<string, ColumnSchema> $columns
     */
    public static function create(
        ?string $entityClass = null,
        ?string $repositoryClass = null,
        ?string $table = 'users',
        ?array $columns = null,
    ): EntitySchema {
        return new EntitySchema(
            entityClass: $entityClass ?? UserFixture::class,
            repositoryClass: $repositoryClass ?? UserRepositoryFixture::class,
            table: $table ?? 'users',
            columns: $columns ?? [
                'id' => new ColumnSchema(
                    propertyName: 'id',
                    propertyType: PropertyTypeEnum::Int,
                    columnName: 'id',
                    columnType: 'int',
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
            ],
        );
    }
}
