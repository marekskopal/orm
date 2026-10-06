<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use PDO;
use PDOStatement;

/** @template T of object */
class Insert extends AbstractQuery
{
    /** @var list<T> */
    private array $entities = [];

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
            throw new \LogicException('No entities to insert');
        }

        $statement = $this->query();
        $this->updateId($statement);
    }

    public function getSql(): string
    {
        if (count($this->entities) === 0) {
            throw new \LogicException('No entities to insert');
        }

        $parts = [
            'INSERT INTO',
            $this->escape($this->schema->table),
            '(' . implode(',', $this->getColumns()) . ')',
            $this->getValuesQuery(),
        ];

        $primaryColumnSchema = $this->schema->getPrimaryColumn();
        if ($primaryColumnSchema->isAutoIncrement) {
            $returningClause = $this->database->getInsertReturningClause($primaryColumnSchema->columnName);
            if ($returningClause !== '') {
                $parts[] = $returningClause;
            }
        }

        return implode(' ', $parts);
    }

    private function query(): PDOStatement
    {
        return $this->database->execute($this->getSql(), $this->getValues());
    }

    private function updateId(PDOStatement $statement): void
    {
        $primaryColumnSchema = $this->schema->getPrimaryColumn();

        // A primary key that is not auto-increment was sent with the row and is never overwritten.
        if (!$primaryColumnSchema->isAutoIncrement) {
            return;
        }

        if ($this->database->getInsertReturningClause($primaryColumnSchema->columnName) !== '') {
            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($this->entities as $i => $entity) {
                $id = $rows[$i][$primaryColumnSchema->columnName] ?? null;
                if (!is_int($id) && !is_string($id)) {
                    throw new \RuntimeException(sprintf('Insert did not return a value for "%s".', $primaryColumnSchema->columnName));
                }

                $this->schemaProvider->setPrimaryKey($entity, (int) $id);
            }

            return;
        }

        // Without RETURNING (MySQL), ids are derived as lastInsertId() + row offset.
        // MySQL's lastInsertId() returns the id of the FIRST row of a multi-row insert,
        // and allocation within one statement is consecutive with
        // innodb_autoinc_lock_mode 0 or 1. With lock mode 2 (the MySQL 8 default)
        // consecutiveness is not guaranteed under concurrent insert load — see README.
        $firstInsertId = (int) $this->database->getPdo()->lastInsertId();
        foreach ($this->entities as $i => $entity) {
            $this->schemaProvider->setPrimaryKey($entity, $firstInsertId + $i);
        }
    }

    /** @return array<string, string> */
    private function getColumns(): array
    {
        return array_map(
            fn(ColumnSchema $column): string => $this->escape($column->columnName),
            $this->schema->getInsertableColumns(),
        );
    }

    private function getValuesQuery(): string
    {
        $placeholder = '(' . implode(',', array_map(fn(ColumnSchema $column): string => '?', $this->schema->getInsertableColumns())) . ')';

        return 'VALUES ' . implode(',', array_fill(0, count($this->entities), $placeholder));
    }

    /** @return list<string|int|float|null> */
    private function getValues(): array
    {
        $values = [];
        foreach ($this->entities as $entity) {
            array_push($values, ...$this->getEntityValues($entity));
        }

        return $values;
    }

    /**
     * @param T $entity
     * @return list<string|int|float|null>
     */
    private function getEntityValues(object $entity): array
    {
        return array_values($this->schemaProvider->extract($entity));
    }
}
