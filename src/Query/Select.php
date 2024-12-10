<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use Iterator;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Query\Enum\DirectionEnum;
use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;
use PDOStatement;

/**
 * @template T of object
 * @phpstan-import-type Where from WhereBuilder
 */
class Select
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

    /** @param class-string<T> $entityClass */
    public function __construct(
        private readonly PDO $pdo,
        private readonly EntityFactory $entityFactory,
        private readonly string $entityClass,
        private readonly EntitySchema $schema,
    ) {
        $this->whereBuilder = new WhereBuilder();
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

        $this->orderBy[] = [$column, $direction];

        return $this;
    }

    /**
     * @param list<string> $columns
     * @return Select<T>
     */
    public function columns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * @param list<string> $columns
     * @return Select<T>
     */
    public function groupBy(array $columns): self
    {
        $this->groupBy = $columns;
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
            . ' FROM ' . $this->schema->table
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

        return array_map(fn($column) => $column->columnName, $this->schema->columns);
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
}
