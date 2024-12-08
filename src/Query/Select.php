<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use Iterator;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Query\Enum\DirectionEnum;
use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;
use PDOStatement;

/** @template T of object */
class Select
{
    /** @var list<array{0: string, 1: string, 2: scalar}> */
    private array $whereParams = [];

    /** @var list<array{0: string, 1: DirectionEnum}> */
    private array $orderBy = [];

    /** @var list<string> */
    private array $columns = [];

    private ?int $limit = null;

    private ?int $offset = null;

    /** @param class-string<T> $entityClass */
    public function __construct(
        private readonly PDO $pdo,
        private readonly EntityFactory $entityFactory,
        private readonly string $entityClass,
        private readonly EntitySchema $schema,
    ) {
    }

    /**
     * @param array<string,scalar>|array{0: string, 1: string, 2: scalar}|list<array{0: string, 1: string, 2: scalar}> $params
     * @return Select<T>
     */
    public function where(array $params = []): self
    {
        if (count($params) === 0) {
            return $this;
        }

        if (
            count($params) === 3
            && is_string($params[0] ?? null)
            && is_string($params[1] ?? null)
            && is_scalar($params[2] ?? null)
        ) {
            /** @var array{0: string, 1: string, 2: scalar} $params */
            $this->whereParams[] = $params;
            return $this;
        }

        /** @var array<string,scalar>|list<array{0: string, 1: string, 2: scalar}> $params */
        foreach ($params as $column => $param) {
            if (is_array($param)) {
                $this->whereParams[] = $param;
                continue;
            }

            /**
             * @var string $column
             * @var scalar $param
             */
            $this->whereParams[] = [$column, '=', $param];
        }

        return $this;
    }

    /** @return Select<T> */
    public function orderBy(string $column, DirectionEnum|string $direction = DirectionEnum::Asc): self
    {
        if (is_string($direction)) {
            $direction = DirectionEnum::from($direction);
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

    /** @return Select<T> */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /** @return Select<T> */
    public function offset(int $offset): self
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
            . $this->getOrderByQuery()
            . $this->getLimitQuery()
            . $this->getOffsetQuery();
    }

    private function query(): PDOStatement
    {
        $pdoStatement = $this->pdo->prepare($this->getSql());
        $pdoStatement->execute(array_map(fn(array $column) => $column[2], $this->whereParams));
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
        if (count($this->whereParams) === 0) {
            return '';
        }

        return ' WHERE ' . implode(' AND ', array_map(fn(array $column): string => $column[0] . $column[1] . '?', $this->whereParams));
    }

    private function getOrderByQuery(): string
    {
        if (count($this->orderBy) === 0) {
            return '';
        }

        return ' ORDER BY ' . implode(', ', array_map(fn(array $column): string => $column[0] . ' ' . $column[1]->value, $this->orderBy));
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
