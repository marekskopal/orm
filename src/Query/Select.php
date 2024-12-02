<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;
use PDOStatement;

class Select
{
    /** @var list<array{0: string, 1: string, 2: scalar}> */
    private array $whereParams = [];

    public function __construct(private readonly PDO $pdo, private readonly EntitySchema $schema,)
    {
    }

    /** @param array<scalar|array{0: string, 1: string, 2: scalar}> $params */
    public function where(array $params = []): self
    {
        $this->addWhereParams($params);

        return $this;
    }

    /** @return array<string, float|int|string> */
    public function fetch(): ?array
    {
        $result = $this->query()->fetch(mode: PDO::FETCH_ASSOC);
        // @phpstan-ignore-next-line return.type
        return $result === false ? null : $result;
    }

    /** @return iterable<array<string, float|int|string>> */
    public function fetchAll(): iterable
    {
        $query = $this->query();
        while ($row = $query->fetch(mode: PDO::FETCH_ASSOC)) {
            // @phpstan-ignore-next-line return.type
            yield $row;
        }
    }

    /** @param array<scalar|array{0: string, 1: string, 2: scalar}> $params */
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
