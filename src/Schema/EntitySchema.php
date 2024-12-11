<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema;

use MarekSkopal\ORM\Repository\RepositoryInterface;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;

readonly class EntitySchema
{
    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @param class-string<RepositoryInterface<covariant T>> $repositoryClass
     * @param array<string, ColumnSchema> $columns
     */
    public function __construct(
        public string $entityClass,
        public string $repositoryClass,
        public string $table,
        public string $tableAlias,
        public array $columns,
    )
    {
    }

    public function getPrimaryColumn(): ColumnSchema
    {
        return array_find($this->columns, fn(ColumnSchema $column): bool => $column->isPrimary)
            ?? throw new \InvalidArgumentException('Primary column schema not found.');
    }

    /** @return array<string, ColumnSchema> */
    public function getInsertableColumns(): array
    {
        return array_filter(
            $this->columns,
            fn(ColumnSchema $column): bool => !$column->isPrimary && ($column->relationType === null || $column->relationType === RelationEnum::ManyToOne),
        );
    }

    public function getColumnByPropertyName(string $propertyName): ColumnSchema
    {
        return $this->columns[$propertyName] ?? throw new \InvalidArgumentException(
            sprintf('Column schema for property "%s" not found.', $propertyName),
        );
    }

    public function getColumnByColumnName(string $columnName): ColumnSchema
    {
        return array_find($this->columns, fn($column) => $column->columnName === $columnName) ?? throw new \InvalidArgumentException(
            sprintf('Column schema for column "%s" not found.', $columnName),
        );
    }
}
