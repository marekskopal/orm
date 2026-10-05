<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema;

use Closure;
use MarekSkopal\ORM\Mapper\ExtensionMapperProvider;
use MarekSkopal\ORM\Relation\RelationResolver;
use MarekSkopal\ORM\Repository\RepositoryInterface;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use ReflectionClass;

readonly class EntitySchema
{
    public ?ColumnSchema $primaryColumn;

    /** @var array<string, ColumnSchema> */
    public array $selectableColumns;

    /** @var array<string, ColumnSchema> written on insert: every owning column, plus a primary key that is not auto-increment */
    public array $insertableColumns;

    /** @var array<string, ColumnSchema> written on update and compared for changes: the insertable columns without the primary key */
    public array $updatableColumns;

    /** @var array<string, ColumnSchema> */
    public array $columnsByColumnName;

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @param class-string<RepositoryInterface<covariant T>> $repositoryClass
     * @param array<string, ColumnSchema> $columns
     * @param (Closure(array<string, mixed>, RelationResolver): object)|null $hydrator generated row-to-entity closure,
     *        set when the schema is loaded from a dumped file; compiled on first use otherwise
     * @param (Closure(object, ExtensionMapperProvider): array<string, string|int|float|null>)|null $extractor generated
     *        entity-to-row closure for the insertable columns, keyed by column name
     * @param (Closure(list<mixed>, ExtensionMapperProvider): array<string, string|int|float|null>)|null $normalizer generated
     *        closure that turns a hydration snapshot (raw values of the updatable columns) into extractor format
     */
    public function __construct(
        public string $entityClass,
        public string $repositoryClass,
        public string $table,
        public string $tableAlias,
        public array $columns,
        public ?Closure $hydrator = null,
        public ?Closure $extractor = null,
        public ?Closure $normalizer = null,
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
            fn(ColumnSchema $column): bool => !($column->isPrimary && $column->isAutoIncrement) && (
                $column->relationType === null
                || $column->relationType === RelationEnum::ManyToOne
                || $column->relationType === RelationEnum::OneToOne
            ),
        );
        $this->updatableColumns = array_filter($this->insertableColumns, fn(ColumnSchema $column): bool => !$column->isPrimary);

        $columnsByColumnName = [];
        foreach ($this->columns as $column) {
            $columnsByColumnName[$column->columnName] = $column;
        }
        $this->columnsByColumnName = $columnsByColumnName;
    }

    /**
     * Returns a lazy proxy that builds the schema on first access. Dumped schema files use it, so
     * loading the file costs one proxy per entity and only the entities a request uses are built.
     *
     * @internal used by schema files written by SchemaDumper
     * @param Closure(): self $factory
     */
    public static function lazy(Closure $factory): self
    {
        return new ReflectionClass(self::class)->newLazyProxy($factory);
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

    /** @return array<string, ColumnSchema> */
    public function getUpdatableColumns(): array
    {
        return $this->updatableColumns;
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
