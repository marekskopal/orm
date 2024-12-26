<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Schema;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Repository\RepositoryInterface;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Repository\UserRepositoryFixture;

class EntitySchemaFixture
{
    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @param class-string<RepositoryInterface<T>> $repositoryClass
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
            tableAlias: 'u',
            columns: $columns ?? [
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
            ],
        );
    }
}
