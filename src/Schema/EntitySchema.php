<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema;

use MarekSkopal\ORM\Repository\RepositoryInterface;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;

readonly class EntitySchema
{
    public ?ColumnSchema $primaryColumn;

    /** @var array<string, ColumnSchema> */
    public array $selectableColumns;

    /** @var array<string, ColumnSchema> */
    public array $insertableColumns;

    /** @var array<string, ColumnSchema> */
    public array $columnsByColumnName;

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
    ) {
        $this->primaryColumn = array_find($this->columns, fn(ColumnSchema $column): bool => $column->isPrimary);

        $this->selectableColumns = array_filter(
            $this->columns,
            fn(ColumnSchema $column): bool => $column->relationType === null
                || $column->relationType === RelationEnum::ManyToOne
                || $column->relationType === RelationEnum::OneToOne,
        );

        $this->insertableColumns = array_filter(
            $this->columns,
            fn(ColumnSchema $column): bool => !$column->isPrimary && (
                $column->relationType === null
                || $column->relationType === RelationEnum::ManyToOne
                || $column->relationType === RelationEnum::OneToOne
            ),
        );

        $columnsByColumnName = [];
        foreach ($this->columns as $column) {
            $columnsByColumnName[$column->columnName] = $column;
        }
        $this->columnsByColumnName = $columnsByColumnName;
    }

    public function getPrimaryColumn(): ColumnSchema
    {
        return $this->primaryColumn ?? throw new \InvalidArgumentException('Primary column schema not found.');
    }

    /** @return array<string, ColumnSchema> */
    public function getSelectableColumns(): array
    {
        return $this->selectableColumns;
    }

    /** @return array<string, ColumnSchema> */
    public function getInsertableColumns(): array
    {
        return $this->insertableColumns;
    }

    public function getColumnByPropertyName(string $propertyName): ColumnSchema
    {
        return $this->columns[$propertyName] ?? throw new \InvalidArgumentException(
            sprintf('Column schema for property "%s" not found.', $propertyName),
        );
    }

    public function getColumnByColumnName(string $columnName): ColumnSchema
    {
        return $this->columnsByColumnName[$columnName] ?? throw new \InvalidArgumentException(
            sprintf('Column schema for column "%s" not found.', $columnName),
        );
    }
}
