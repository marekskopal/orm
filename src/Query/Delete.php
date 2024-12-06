<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;
use PDOStatement;

class Delete
{
    /** @var list<int> */
    private array $ids = [];

    public function __construct(
        private readonly PDO $pdo,
        private readonly EntitySchema $entitySchema,
        private readonly ColumnSchema $primaryColumnSchema,
    )
    {
    }

    /** @param list<int> $ids */
    public function ids(array $ids = []): self
    {
        $this->ids = array_merge($this->ids, $ids);

        return $this;
    }

    public function execute(): void
    {
        if (count($this->ids) === 0) {
            return;
        }

        $this->query();
    }

    private function query(): PDOStatement
    {
        $pdoStatement = $this->pdo->prepare($this->getSql());
        $pdoStatement->execute($this->ids);
        return $pdoStatement;
    }

    private function getSql(): string
    {
        return implode(' ', [
            'DELETE FROM',
            $this->entitySchema->table,
            $this->getWhereQuery(),
        ]);
    }

    private function getWhereQuery(): string
    {
        return 'WHERE ' . $this->primaryColumnSchema->columnName . ' IN (' . implode(
            ',',
            array_map(fn($item): string => '?', $this->ids),
        ) . ')';
    }
}
