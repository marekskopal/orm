<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use Iterator;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Exception\ExceptionFactory;
use MarekSkopal\ORM\Query\Enum\DirectionEnum;
use MarekSkopal\ORM\Query\Expression\RawExpression;
use MarekSkopal\ORM\Query\Model\Join;
use MarekSkopal\ORM\Query\Where\WhereBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use PDO;
use PDOStatement;

/**
 * @template T of object
 * @phpstan-import-type Where from WhereBuilder
 */
class Select extends AbstractQuery
{
    private readonly WhereBuilder $whereBuilder;

    /** @var list<array{0: string, 1: DirectionEnum}> */
    private array $orderBy = [];

    /** @var list<string> */
    private array $columns = [];

    /** @var list<string> */
    private array $groupBy = [];

    private ?int $limit = null;

    private ?int $offset = null;

    /** @var array<string, Join> */
    private array $joins = [];

    /** @var array<string, string> relation path (e.g. "address.country") => table alias used for its join */
    private array $joinAliases = [];

    /** @var array<string, true> */
    private array $usedAliases = [];

    /** @var list<string> */
    private array $with = [];

    /** @param class-string<T> $entityClass */
    public function __construct(
        DatabaseInterface $database,
        string $entityClass,
        EntitySchema $schema,
        private readonly EntityFactory $entityFactory,
        private readonly SchemaProvider $schemaProvider,
    ) {
        parent::__construct($database, $entityClass, $schema);

        $this->whereBuilder = new WhereBuilder($this);
        $this->usedAliases[$schema->tableAlias] = true;
    }

    /**
     * @param Where $params
     * @return Select<T>
     */
    public function where(array|callable $params): self
    {
        $this->whereBuilder->where($params);

        return $this;
    }

    /** @return Select<T> */
    public function orderBy(string|RawExpression $column, DirectionEnum|string $direction = DirectionEnum::Asc): self
    {
        if (is_string($direction)) {
            $direction = DirectionEnum::from(strtoupper($direction));
        }

        $this->orderBy[] = [$this->parseColumn($column), $direction];

        return $this;
    }

    /**
     * @param list<string|RawExpression> $columns
     * @return Select<T>
     */
    public function columns(array $columns): self
    {
        $this->columns = array_map(fn(string|RawExpression $column): string => $this->parseColumn($column), $columns);
        return $this;
    }

    /**
     * @param list<string|RawExpression> $columns
     * @return Select<T>
     */
    public function groupBy(array $columns): self
    {
        $this->groupBy = array_map(fn(string|RawExpression $column): string => $this->parseColumn($column), $columns);
        return $this;
    }

    /** @return Select<T> */
    public function limit(?int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /** @return Select<T> */
    public function offset(?int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /** @return Select<T> */
    public function join(
        string $column,
        string $referenceTable,
        string $referenceTableAlias,
        string $referenceColumn,
        ?string $tableAlias = null,
    ): self {
        $key = ($tableAlias ?? $this->schema->tableAlias) . '.' . $column;

        if (isset($this->joins[$key])) {
            return $this;
        }

        $this->usedAliases[$referenceTableAlias] = true;
        $this->joins[$key] = new Join(
            $tableAlias ?? $this->schema->tableAlias,
            $column,
            $referenceTable,
            $referenceTableAlias,
            $referenceColumn,
        );
        return $this;
    }

    /**
     * Eager-load ManyToOne / OneToOne relations to avoid N+1 queries.
     *
     * @return Select<T>
     */
    public function with(string ...$propertyNames): self
    {
        foreach ($propertyNames as $propertyName) {
            $this->with[] = $propertyName;
        }

        return $this;
    }

    /** @return T|null */
    public function fetchOne(): ?object
    {
        $result = $this->query($this->buildSql(limit: 1, offset: $this->offset))->fetch(mode: PDO::FETCH_ASSOC);
        // @phpstan-ignore-next-line argument.type
        return $result === false ? null : $this->entityFactory->create($this->entityClass, $result);
    }

    /** @return Iterator<T> */
    public function fetchAll(): Iterator
    {
        if ($this->with === []) {
            $query = $this->query();
            while ($row = $query->fetch(mode: PDO::FETCH_ASSOC)) {
                // @phpstan-ignore-next-line argument.type
                yield $this->entityFactory->create($this->entityClass, $row);
            }
            return;
        }

        /** @var list<array<string, float|int|string|null>> $rows */
        $rows = $this->query()->fetchAll(PDO::FETCH_ASSOC);
        $this->preloadWith($rows);
        foreach ($rows as $row) {
            // @phpstan-ignore-next-line argument.type
            yield $this->entityFactory->create($this->entityClass, $row);
        }
    }

    /** @return array<string, mixed>|null */
    public function fetchAssocOne(): ?array
    {
        /** @var array<string, mixed>|false $result */
        $result = $this->query($this->buildSql(limit: 1, offset: $this->offset))->fetch(mode: PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /** @return Iterator<array<string, mixed>> */
    public function fetchAssocAll(): Iterator
    {
        $query = $this->query();
        while ($row = $query->fetch(mode: PDO::FETCH_ASSOC)) {
            /** @var array<string, mixed> $row */
            yield $row;
        }
    }

    /**
     * Counts the rows matching the current conditions. Selected columns, ORDER BY,
     * LIMIT and OFFSET are ignored; the builder itself is left untouched.
     */
    public function count(): int
    {
        /** @var array{c: int|string} $result */
        $result = $this->query($this->getCountSql())->fetch(mode: PDO::FETCH_ASSOC);
        return (int) $result['c'];
    }

    public function getSql(): string
    {
        return $this->buildSql(limit: $this->limit, offset: $this->offset);
    }

    public function getCountSql(): string
    {
        return $this->buildSql(columns: ['count(*) as c'], withOrderBy: false);
    }

    /** @param list<string>|null $columns Overrides the selected columns when given. */
    private function buildSql(?array $columns = null, ?int $limit = null, ?int $offset = null, bool $withOrderBy = true): string
    {
        // The where clause must be built first: resolving relation paths registers joins.
        $whereQuery = $this->getWhereQuery();

        return 'SELECT '
            . implode(',', $columns ?? $this->getColumns())
            . ' FROM ' . $this->escape($this->schema->table) . ' ' . $this->escape($this->schema->tableAlias)
            . $this->getJoinsQuery()
            . $whereQuery
            . $this->getGroupByQuery()
            . ($withOrderBy ? $this->getOrderByQuery() : '')
            . ($limit !== null ? ' LIMIT ' . $limit : '')
            . ($offset !== null ? ' OFFSET ' . $offset : '');
    }

    public function getWhereBuilder(): WhereBuilder
    {
        return $this->whereBuilder;
    }

    /** @internal */
    public function parseColumn(string|RawExpression $column): string
    {
        if ($column instanceof RawExpression) {
            return $column->expression;
        }

        $parts = explode('.', $column);
        $partsCount = count($parts);

        if ($partsCount === 1) {
            if (preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid column name "%s". Use %s for SQL expressions.', $column, RawExpression::class),
                );
            }

            // A name that is neither a property nor a mapped column is passed through verbatim,
            // so columns that exist only in the database remain addressable.
            $columnSchema = $this->resolveColumnSchema($this->schema, $column);

            return $this->escape($this->schema->tableAlias) . '.' . $this->escape($columnSchema->columnName ?? $column);
        }

        $entitySchema = $this->schema;
        $tableAlias = $this->schema->tableAlias;
        $relationPath = '';
        for ($i = 0; $i < $partsCount - 1; $i++) {
            $columnSchema = $this->resolveColumnSchema($entitySchema, $parts[$i]);

            if ($columnSchema?->relationEntityClass === null) {
                throw new \InvalidArgumentException(
                    sprintf('"%s" in "%s" is not a relation of entity "%s".', $parts[$i], $column, $entitySchema->entityClass),
                );
            }

            $relationEntitySchema = $this->schemaProvider->getEntitySchema($columnSchema->relationEntityClass);

            // Aliases are assigned per relation path, not per table, so two relations to the
            // same table (or a self-referencing relation) get distinct aliases.
            $relationPath = $relationPath === '' ? $parts[$i] : $relationPath . '.' . $parts[$i];
            $relationTableAlias = $this->resolveJoinAlias($relationPath, $relationEntitySchema->tableAlias);

            $this->join(
                column: $columnSchema->columnName,
                referenceTable: $relationEntitySchema->table,
                referenceTableAlias: $relationTableAlias,
                referenceColumn: $relationEntitySchema->getPrimaryColumn()->columnName,
                tableAlias: $tableAlias,
            );

            $entitySchema = $relationEntitySchema;
            $tableAlias = $relationTableAlias;
        }

        $relationColumnSchema = $this->resolveColumnSchema($entitySchema, $parts[$partsCount - 1])
            ?? throw new \InvalidArgumentException(
                sprintf('Column "%s" in "%s" not found on entity "%s".', $parts[$partsCount - 1], $column, $entitySchema->entityClass),
            );

        return $this->escape($tableAlias) . '.' . $this->escape($relationColumnSchema->columnName);
    }

    /**
     * Resolves a segment of a column path against an entity: property names take precedence,
     * then database column names, so both "firstName" and "first_name" address the same column.
     */
    private function resolveColumnSchema(EntitySchema $entitySchema, string $name): ?ColumnSchema
    {
        return $entitySchema->columns[$name] ?? $entitySchema->columnsByColumnName[$name] ?? null;
    }

    /**
     * Returns the alias to use for the join of the given relation path. The relation entity's
     * own table alias is used when it is still free; otherwise a path-based alias is derived.
     */
    private function resolveJoinAlias(string $relationPath, string $preferredAlias): string
    {
        if (isset($this->joinAliases[$relationPath])) {
            return $this->joinAliases[$relationPath];
        }

        $alias = $preferredAlias;
        if (isset($this->usedAliases[$alias])) {
            $alias = $preferredAlias . '_' . str_replace('.', '_', $relationPath);

            $base = $alias;
            $suffix = 2;
            while (isset($this->usedAliases[$alias])) {
                $alias = $base . '_' . $suffix;
                $suffix++;
            }
        }

        $this->joinAliases[$relationPath] = $alias;
        $this->usedAliases[$alias] = true;

        return $alias;
    }

    /** @param list<array<string, float|int|string|null>> $rows */
    private function preloadWith(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach ($this->with as $propertyName) {
            $columnSchema = $this->schema->getColumnByPropertyName($propertyName);

            if (
                $columnSchema->relationType !== RelationEnum::ManyToOne
                && $columnSchema->relationType !== RelationEnum::OneToOne
            ) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Eager loading via with() is currently supported only for ManyToOne and OneToOne relations; property "%s" is not eligible.',
                        $propertyName,
                    ),
                );
            }

            $relationEntityClass = $columnSchema->relationEntityClass
                ?? throw new \RuntimeException('Relation entity class not found');

            $columnName = $columnSchema->columnName;
            $ids = [];
            foreach ($rows as $row) {
                $value = $row[$columnName] ?? null;
                if ($value === null) {
                    continue;
                }
                $ids[(int) $value] = true;
            }

            if ($ids === []) {
                continue;
            }

            $primaryColumnSchema = $this->schemaProvider->getPrimaryColumnSchema($relationEntityClass);
            $relationSchema = $this->schemaProvider->getEntitySchema($relationEntityClass);

            $select = new Select($this->database, $relationEntityClass, $relationSchema, $this->entityFactory, $this->schemaProvider);
            // Materialize the iterator to populate EntityCache; subsequent ManyToOne/OneToOne
            // lookups during hydration will hit the cache and skip per-row queries.
            iterator_to_array($select->where([$primaryColumnSchema->columnName, 'IN', array_keys($ids)])->fetchAll());
        }
    }

    private function query(?string $sql = null): PDOStatement
    {
        $sql ??= $this->getSql();

        try {
            $pdoStatement = $this->pdo->prepare($sql);
            $pdoStatement->execute($this->whereBuilder->getParams());
            return $pdoStatement;
        } catch (\PDOException $e) {
            throw ExceptionFactory::create($e, $sql);
        }
    }

    /** @return array<string> */
    private function getColumns(): array
    {
        if (count($this->columns) > 0) {
            return $this->columns;
        }

        return array_map(
            fn(ColumnSchema $column): string => $this->escape($this->schema->tableAlias) . '.' . $this->escape($column->columnName),
            $this->schema->getSelectableColumns(),
        );
    }

    private function getWhereQuery(): string
    {
        $where = $this->whereBuilder->build();
        if ($where === '') {
            return '';
        }

        return ' WHERE ' . $where;
    }

    private function getOrderByQuery(): string
    {
        if (count($this->orderBy) === 0) {
            return '';
        }

        return ' ORDER BY ' . implode(', ', array_map(fn(array $column): string => $column[0] . ' ' . $column[1]->value, $this->orderBy));
    }

    private function getGroupByQuery(): string
    {
        if (count($this->groupBy) === 0) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', $this->groupBy);
    }

    private function getJoinsQuery(): string
    {
        if (count($this->joins) === 0) {
            return '';
        }

        return ' ' . implode(
            ' ',
            array_map(
                fn(Join $join): string => 'LEFT JOIN ' . $this->escape($join->referenceTable) . ' ' . $this->escape(
                    $join->referenceTableAlias,
                ) . ' ON ' . $this->escape(
                    $join->referenceTableAlias,
                ) . '.' . $this->escape(
                    $join->referenceColumn,
                ) . '=' . $this->escape(
                    $join->tableAlias,
                ) . '.' . $this->escape(
                    $join->column,
                ),
                $this->joins,
            ),
        );
    }
}
