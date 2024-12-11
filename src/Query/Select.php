<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use Iterator;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Query\Enum\DirectionEnum;
use MarekSkopal\ORM\Query\Model\Join;
use MarekSkopal\ORM\Query\Where\WhereBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use PDO;
use PDOStatement;

/**
 * @template T of object
 * @phpstan-import-type Where from WhereBuilder
 */
class Select
{
    private readonly EntitySchema $schema;

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

    /** @param class-string<T> $entityClass */
    public function __construct(
        private readonly PDO $pdo,
        private readonly EntityFactory $entityFactory,
        private readonly string $entityClass,
        private readonly SchemaProvider $schemaProvider,
    ) {
        $this->schema = $this->schemaProvider->getEntitySchema($entityClass);
        $this->whereBuilder = new WhereBuilder($this);
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
    public function orderBy(string $column, DirectionEnum|string $direction = DirectionEnum::Asc): self
    {
        if (is_string($direction)) {
            $direction = DirectionEnum::from(strtoupper($direction));
        }

        $this->orderBy[] = [$this->parseColumn($column), $direction];

        return $this;
    }

    /**
     * @param list<string> $columns
     * @return Select<T>
     */
    public function columns(array $columns): self
    {
        $this->columns = array_map(fn(string $column): string => $this->parseColumn($column), $columns);
        return $this;
    }

    /**
     * @param list<string> $columns
     * @return Select<T>
     */
    public function groupBy(array $columns): self
    {
        $this->groupBy = array_map(fn(string $column): string => $this->parseColumn($column), $columns);
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
    public function join(string $column, string $referenceTable, string $referenceTableAlias, string $referenceColumn): self
    {
        if (isset($this->joins[$column])) {
            return $this;
        }

        $this->joins[$column] = new Join($column, $referenceTable, $referenceTableAlias, $referenceColumn);
        return $this;
    }

    /** @return T|null */
    public function fetchOne(): ?object
    {
        $result = $this->query()->fetch(mode: PDO::FETCH_ASSOC);
        // @phpstan-ignore-next-line return.type
        return $result === false ? null : $this->entityFactory->create($this->entityClass, $result);
    }

    /** @return Iterator<T> */
    public function fetchAll(): Iterator
    {
        $query = $this->query();
        while ($row = $query->fetch(mode: PDO::FETCH_ASSOC)) {
            // @phpstan-ignore-next-line return.type
            yield $this->entityFactory->create($this->entityClass, $row);
        }
    }

    /** @return array<string, mixed>|null */
    public function fetchAssocOne(): ?array
    {
        $result = $this->query()->fetch(mode: PDO::FETCH_ASSOC);
        // @phpstan-ignore-next-line return.type
        return $result === false ? null : $result;
    }

    /** @return Iterator<array<string, mixed>> */
    public function fetchAssocAll(): Iterator
    {
        $query = $this->query();
        while ($row = $query->fetch(mode: PDO::FETCH_ASSOC)) {
            // @phpstan-ignore-next-line return.type
            yield $row;
        }
    }

    public function count(): int
    {
        $this->columns(['count(*) as c']);

        /** @var array{c: int} $result */
        $result = $this->query()->fetch(mode: PDO::FETCH_ASSOC);
        return $result['c'];
    }

    public function getSql(): string
    {
        return 'SELECT '
            . implode(',', $this->getColumns())
            . ' FROM ' . $this->schema->table . ' ' . $this->schema->tableAlias
            . $this->getJoinsQuery()
            . $this->getWhereQuery()
            . $this->getGroupByQuery()
            . $this->getOrderByQuery()
            . $this->getLimitQuery()
            . $this->getOffsetQuery();
    }

    public function getWhereBuilder(): WhereBuilder
    {
        return $this->whereBuilder;
    }

    /** @internal */
    public function parseColumn(string $column): string
    {
        $parts = explode('.', $column);

        if (count($parts) === 1) {
            return $this->schema->tableAlias . '.' . $column;
        }

        $entitySchema = $this->schemaProvider->getEntitySchema($this->entityClass);
        $columnSchema = $entitySchema->getColumnByPropertyName($parts[0]);
        if ($columnSchema->relationEntityClass === null) {
            throw new \InvalidArgumentException('Column is not relation');
        }

        $relationEntitySchema = $this->schemaProvider->getEntitySchema($columnSchema->relationEntityClass);
        $relationColumnSchema = $relationEntitySchema->getColumnByColumnName($parts[1]);

        $this->join(
            column: $columnSchema->columnName,
            referenceTable: $relationEntitySchema->table,
            referenceTableAlias: $relationEntitySchema->tableAlias,
            referenceColumn: $relationEntitySchema->getPrimaryColumn()->columnName,
        );

        return $relationEntitySchema->tableAlias . '.' . $relationColumnSchema->columnName;
    }

    private function query(): PDOStatement
    {
        $pdoStatement = $this->pdo->prepare($this->getSql());
        $pdoStatement->execute($this->whereBuilder->getParams());
        return $pdoStatement;
    }

    /** @return array<string> */
    private function getColumns(): array
    {
        if (count($this->columns) > 0) {
            return $this->columns;
        }

        return array_map(fn(ColumnSchema $column): string => $this->schema->tableAlias . '.' . $column->columnName, $this->schema->columns);
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

    private function getLimitQuery(): string
    {
        if ($this->limit === null) {
            return '';
        }

        return ' LIMIT ' . $this->limit;
    }

    private function getOffsetQuery(): string
    {
        if ($this->offset === null) {
            return '';
        }

        return ' OFFSET ' . $this->offset;
    }

    private function getJoinsQuery(): string
    {
        if (count($this->joins) === 0) {
            return '';
        }

        return ' ' . implode(
            ' ',
            array_map(
                fn(Join $join): string => 'LEFT JOIN ' . $join->referenceTable . ' ' . $join->refenceTableAlias . ' ON ' . $join->refenceTableAlias . '.' . $join->referenceColumn . '=' . $this->schema->tableAlias . '.' . $join->column,
                $this->joins,
            ),
        );
    }
}
