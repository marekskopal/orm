<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;
use PDOStatement;

/** @template T of object */
class Insert
{
    /** @var list<T> */
    private array $entities = [];

    public function __construct(private readonly PDO $pdo, private readonly EntitySchema $schema, private readonly Mapper $mapper)
    {
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
        $this->query();
        $this->updateId();
    }

    public function getSql(): string
    {
        if (count($this->entities) === 0) {
            throw new \LogicException('No entities to insert');
        }

        return implode(' ', [
            'INSERT INTO',
            $this->schema->table,
            '(' . implode(',', $this->getColumns()) . ')',
            $this->getValuesQuery(),
        ]);
    }

    private function query(): PDOStatement
    {
        $pdoStatement = $this->pdo->prepare($this->getSql());
        $pdoStatement->execute($this->getValues());
        return $pdoStatement;
    }

    private function updateId(): void
    {
        $lastInsertId = $this->pdo->lastInsertId();
        foreach ($this->entities as $entity) {
            // @phpstan-ignore-next-line property.dynamicName
            $entity->{$this->schema->getPrimaryColumn()->columnName} = (int) $lastInsertId;
        }
    }

    /** @return array<string, string> */
    private function getColumns(): array
    {
        return array_map(
            fn(ColumnSchema $column): string => $column->columnName,
            $this->schema->getInsertableColumns(),
        );
    }

    private function getValuesQuery(): string
    {
        $entitiesQuery = [];
        foreach ($this->entities as $entity) {
            $entitiesQuery[] = '(' . implode(
                ',',
                array_map(fn(ColumnSchema $column): string => ':' . $column->columnName, $this->schema->getInsertableColumns()),
            ) . ')';
        }

        return 'VALUES ' . implode(',', $entitiesQuery);
    }

    /** @return list<string> */
    private function getValues(): array
    {
        $values = [];
        foreach ($this->entities as $entity) {
            $values += array_map(
                // @phpstan-ignore-next-line argument.type property.dynamicName
                fn(ColumnSchema $column): string => (string) $this->mapper->mapToColumn($column, $entity->{$column->propertyName}),
                $this->schema->getInsertableColumns(),
            );
        }

        return array_values($values);
    }
}
