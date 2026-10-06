<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use PDOStatement;

/** @template T of object */
class Delete extends AbstractQuery
{
    /** @var list<T> */
    private array $entities = [];

    /** @param class-string<T> $entityClass */
    public function __construct(
        DatabaseInterface $database,
        string $entityClass,
        EntitySchema $schema,
        private readonly ColumnSchema $primaryColumnSchema,
        private readonly SchemaProvider $schemaProvider,
    )
    {
        parent::__construct($database, $entityClass, $schema);
    }

    /**
     * @param T $entity
     * @return self<T>
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
            $this->escape($this->schema->table),
            $this->getWhereQuery(),
        ]);
    }

    private function query(): PDOStatement
    {
        return $this->database->execute($this->getSql(), $this->getIds());
    }

    private function getWhereQuery(): string
    {
        return 'WHERE ' . $this->escape($this->primaryColumnSchema->columnName) . ' IN (' . implode(
            ',',
            array_map(fn($item): string => '?', $this->entities),
        ) . ')';
    }

    /** @return list<int|string> */
    private function getIds(): array
    {
        return array_map(fn(object $entity): int|string => $this->schemaProvider->getPrimaryKeyValue($entity), $this->entities);
    }
}
