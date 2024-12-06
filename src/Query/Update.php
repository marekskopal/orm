<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;
use PDOStatement;

/** @template T of object */
class Update
{
    /** @var T */
    private object $entity;

    public function __construct(private readonly PDO $pdo, private readonly EntitySchema $schema, private readonly Mapper $mapper)
    {
    }

    /**
     * @param T $entity
     * @return Insert<T>
     */
    public function entity(object $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function execute(): void
    {
        $this->query();
    }

    public function getSql(): string
    {
        if (!isset($this->entity)) {
            throw new \LogicException('No entity to update');
        }

        return implode(' ', [
            'UPDATE',
            $this->schema->table,
            'SET',
            $this->getSetQuery(),
        ]);
    }

    private function query(): PDOStatement
    {
        $pdoStatement = $this->pdo->prepare($this->getSql());
        $pdoStatement->execute($this->getValues());
        return $pdoStatement;
    }

    private function getSetQuery(): string
    {
        return implode(',', array_map(
            fn(ColumnSchema $column): string => $column->columnName . '=:' . $column->propertyName,
            $this->schema->getInsertableColumns(),
        ));
    }

    /** @return array<string, string|int|float|null> */
    private function getValues(): array
    {
        return array_map(
        // @phpstan-ignore-next-line argument.type property.dynamicName
            fn(ColumnSchema $column): string|int|float|null => $this->mapper->mapToColumn($column, $this->entity->{$column->propertyName}),
            $this->schema->getInsertableColumns(),
        );
    }
}
