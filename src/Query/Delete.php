<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;
use PDOStatement;

/** @template T of object */
class Delete
{
    /** @var list<T> */
    private array $entities = [];

    public function __construct(
        private readonly PDO $pdo,
        private readonly EntitySchema $entitySchema,
        private readonly ColumnSchema $primaryColumnSchema,
    ) {
    }

    /**
     * @param T $entity
     * @return Insert<T>
     */
    public function entity(object $entity): self
    {
        $this->entities[] = $entity;

        return $this;
    }

    public function execute(): void
    {
        if (count($this->entities) === 0) {
            return;
        }

        $this->query();
    }

    public function getSql(): string
    {
        return implode(' ', [
            'DELETE FROM',
            $this->entitySchema->table,
            $this->getWhereQuery(),
        ]);
    }

    private function query(): PDOStatement
    {
        $pdoStatement = $this->pdo->prepare($this->getSql());
        $pdoStatement->execute($this->getIds());
        return $pdoStatement;
    }

    private function getWhereQuery(): string
    {
        return 'WHERE ' . $this->primaryColumnSchema->columnName . ' IN (' . implode(
            ',',
            array_map(fn($item): string => '?', $this->entities),
        ) . ')';
    }

    private function getIds(): array
    {
        return array_map(
            fn($entity): int => $entity->{$this->primaryColumnSchema->columnName},
            $this->entities,
        );
    }
}
