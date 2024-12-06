<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;
use PDOStatement;

class Update
{
    /** @var list<array{0: string, 1: string, 2: scalar}> */
    private array $values = [];

    public function __construct(private readonly PDO $pdo, private readonly EntitySchema $schema,)
    {
    }

    /** @param array<string, scalar> $values */
    public function values(array $values = []): self
    {
        $this->values[] = $values;

        return $this;
    }

    public function execute(): void
    {
        $this->query();
    }

    private function query(): PDOStatement
    {
        $pdoStatement = $this->pdo->prepare($this->getSql());
        $pdoStatement->execute($this->values);
        return $pdoStatement;
    }

    private function getSql(): string
    {
        return implode(' ', [
            'INSERT INTO',
            $this->schema->table,
            implode(',', $this->getColumns()),
            $this->getValuesQuery(),
        ]);
    }

    /** @return array<string, string> */
    private function getColumns(): array
    {
        return array_map(fn(ColumnSchema $column): string => $column->columnName, $this->schema->columns);
    }

    private function getValuesQuery(): string
    {
        return 'VALUES(' . implode(
            ',',
            array_map(fn(ColumnSchema $column): string => ':' . $column->columnName, $this->schema->columns),
        ) . ')';
    }
}
