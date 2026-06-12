<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Exception\ExceptionFactory;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;

/** @template T of object */
class Insert extends AbstractQuery
{
    /** @var list<T> */
    private array $entities = [];

    /** @param class-string<T> $entityClass */
    public function __construct(DatabaseInterface $database, string $entityClass, EntitySchema $schema, private readonly Mapper $mapper)
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

        $primaryColumnSchema = $this->schema->getPrimaryColumn();

        if ($this->database->getInsertReturningClause($primaryColumnSchema->columnName) !== '') {
            $this->executeWithReturning($primaryColumnSchema);
            return;
        }

        $this->executePerRow($primaryColumnSchema);
    }

    public function getSql(): string
    {
        if (count($this->entities) === 0) {
            throw new \LogicException('No entities to insert');
        }

        return $this->buildSql(count($this->entities));
    }

    private function buildSql(int $rowsCount): string
    {
        $parts = [
            'INSERT INTO',
            $this->escape($this->schema->table),
            '(' . implode(',', $this->getColumns()) . ')',
            $this->getValuesQuery($rowsCount),
        ];

        $returningClause = $this->database->getInsertReturningClause($this->schema->getPrimaryColumn()->columnName);
        if ($returningClause !== '') {
            $parts[] = $returningClause;
        }

        return implode(' ', $parts);
    }

    private function executeWithReturning(ColumnSchema $primaryColumnSchema): void
    {
        try {
            $sql = $this->getSql();
            $pdoStatement = $this->pdo->prepare($sql);
            $pdoStatement->execute($this->getValues());
        } catch (\PDOException $e) {
            throw ExceptionFactory::create($e, $sql);
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($this->entities as $i => $entity) {
            // @phpstan-ignore-next-line property.dynamicName
            $entity->{$primaryColumnSchema->propertyName} = (int) $rows[$i][$primaryColumnSchema->columnName];
        }
    }

    /**
     * Without a RETURNING clause there is no reliable way to map database-generated
     * ids back to entities in a multi-row insert (auto-increment values are not
     * guaranteed to be contiguous), so each entity is inserted by its own statement
     * and its id read back via lastInsertId().
     */
    private function executePerRow(ColumnSchema $primaryColumnSchema): void
    {
        $sql = $this->buildSql(1);

        try {
            $pdoStatement = $this->pdo->prepare($sql);

            foreach ($this->entities as $entity) {
                $pdoStatement->execute($this->getEntityValues($entity));
                // @phpstan-ignore-next-line property.dynamicName
                $entity->{$primaryColumnSchema->propertyName} = (int) $this->pdo->lastInsertId();
            }
        } catch (\PDOException $e) {
            throw ExceptionFactory::create($e, $sql);
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

    private function getValuesQuery(int $rowsCount): string
    {
        $placeholder = '(' . implode(',', array_map(fn(ColumnSchema $column): string => '?', $this->schema->getInsertableColumns())) . ')';

        return 'VALUES ' . implode(',', array_fill(0, $rowsCount, $placeholder));
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
        $values = [];
        foreach ($this->schema->getInsertableColumns() as $column) {
            // @phpstan-ignore-next-line argument.type property.dynamicName
            $values[] = $this->mapper->mapToColumn($column, $entity->{$column->propertyName});
        }

        return $values;
    }
}
