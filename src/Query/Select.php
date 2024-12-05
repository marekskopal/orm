<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use Iterator;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;
use PDOStatement;

/** @template T of object */
class Select
{
    /** @var list<array{0: string, 1: string, 2: scalar}> */
    private array $whereParams = [];

    /** @param class-string<T> $entityClass */
    public function __construct(
        private readonly PDO $pdo,
        private readonly EntityFactory $entityFactory,
        private readonly string $entityClass,
        private readonly EntitySchema $schema,
    ) {
    }

    /**
     * @param array<string|scalar>|list<array{0: string, 1: string, 2: scalar}> $params
     * @return Select<T>
     */
    public function where(array $params = []): self
    {
        $this->addWhereParams($params);

        return $this;
    }

    /** @return T|null */
    public function fetch(): ?object
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

    /** @param array<string|scalar>|list<array{0: string, 1: string, 2: scalar}> $params */
    private function addWhereParams(array $params): void
    {
        foreach ($params as $column => $param) {
            if (is_array($param)) {
                $this->whereParams[] = $param;
                continue;
            }

            $this->whereParams[] = [$column, '=', $param];
        }
    }

    private function query(): PDOStatement
    {
        $pdoStatement = $this->pdo->prepare($this->getSql());
        $pdoStatement->execute(array_map(fn(array $column) => $column[2], $this->whereParams));
        return $pdoStatement;
    }

    private function getSql(): string
    {
        return 'SELECT ' . implode(',', $this->getColumns()) . ' FROM ' . $this->schema->table . $this->getWhereQuery();
    }

    /** @return array<string, string> */
    private function getColumns(): array
    {
        return array_map(fn($column) => $column->columnName, $this->schema->columns);
    }

    public function getWhereQuery(): string
    {
        if (count($this->whereParams) === 0) {
            return '';
        }

        return ' WHERE ' . implode(' AND ', array_map(fn(array $column): string => $column[0] . $column[1] . ' ?', $this->whereParams));
    }
}
