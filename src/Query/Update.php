<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Exception\ExceptionFactory;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use PDOStatement;

/** @template T of object */
class Update extends AbstractQuery
{
    /** @var T */
    private object $entity;

    /** @var array<string, string|int|float|null>|null column name => value; null writes every updatable column */
    private ?array $values = null;

    /** @param class-string<T> $entityClass */
    public function __construct(
        DatabaseInterface $database,
        string $entityClass,
        EntitySchema $schema,
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
        $this->entity = $entity;

        return $this;
    }

    /**
     * Restricts the update to the given columns, e.g. the ones that changed since the entity was read.
     *
     * @param array<string, string|int|float|null> $values column name => database value
     * @return self<T>
     */
    public function values(array $values): self
    {
        $this->values = $values;

        return $this;
    }

    public function execute(): void
    {
        if ($this->values === []) {
            return;
        }

        $this->query();
    }

    public function getSql(): string
    {
        if (!isset($this->entity)) {
            throw new \LogicException('No entity to update');
        }

        return implode(' ', [
            'UPDATE',
            $this->escape($this->schema->table),
            'SET',
            implode(',', array_map(fn(string $column): string => $this->escape($column) . '=?', array_keys($this->getColumnValues()))),
            'WHERE ' . $this->escape($this->schema->getPrimaryColumn()->columnName) . '=?',
        ]);
    }

    private function query(): PDOStatement
    {
        try {
            $sql = $this->getSql();
            $pdoStatement = $this->pdo->prepare($sql);
            $pdoStatement->execute([...array_values($this->getColumnValues()), $this->schemaProvider->getPrimaryKeyValue($this->entity)]);
            return $pdoStatement;
        } catch (\PDOException $e) {
            throw ExceptionFactory::create($e, $sql);
        }
    }

    /** @return array<string, string|int|float|null> */
    private function getColumnValues(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        return array_intersect_key($this->schemaProvider->extract($this->entity), $this->getUpdatableColumnNames());
    }

    /** @return array<string, true> */
    private function getUpdatableColumnNames(): array
    {
        $columnNames = [];
        foreach ($this->schema->getUpdatableColumns() as $column) {
            $columnNames[$column->columnName] = true;
        }

        return $columnNames;
    }
}
