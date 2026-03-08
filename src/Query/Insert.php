<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Exception\ExceptionFactory;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;
use PDOStatement;

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

        $returningClause = $this->database->getInsertReturningClause($this->schema->getPrimaryColumn()->columnName);
        if ($returningClause !== '') {
            $parts[] = $returningClause;
        }

        return implode(' ', $parts);
    }

    private function query(): PDOStatement
    {
        try {
            $sql = $this->getSql();
            $pdoStatement = $this->pdo->prepare($sql);
            $pdoStatement->execute($this->getValues());
            return $pdoStatement;
        } catch (\PDOException $e) {
            throw ExceptionFactory::create($e, $sql);
        }
    }

    private function updateId(PDOStatement $statement): void
    {
        $primaryColumnSchema = $this->schema->getPrimaryColumn();
        $primaryPropertyName = $primaryColumnSchema->propertyName;

        $returningClause = $this->database->getInsertReturningClause($primaryColumnSchema->columnName);
        if ($returningClause !== '') {
            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($this->entities as $i => $entity) {
                // @phpstan-ignore-next-line property.dynamicName
                $entity->{$primaryPropertyName} = (int) $rows[$i][$primaryColumnSchema->columnName];
            }
        } else {
            $firstInsertId = (int) $this->pdo->lastInsertId();
            foreach ($this->entities as $i => $entity) {
                // @phpstan-ignore-next-line property.dynamicName
                $entity->{$primaryPropertyName} = $firstInsertId + $i;
            }
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
        $entitiesQuery = [];
        foreach ($this->entities as $entity) {
            $entitiesQuery[] = '(' . implode(
                ',',
                array_map(fn(ColumnSchema $column): string => '?', $this->schema->getInsertableColumns()),
            ) . ')';
        }

        return 'VALUES ' . implode(',', $entitiesQuery);
    }

    /** @return list<string|int|float|null> */
    private function getValues(): array
    {
        $values = [];
        foreach ($this->entities as $entity) {
            $values = array_merge($values, array_values(array_map(
                // @phpstan-ignore-next-line argument.type property.dynamicName
                fn(ColumnSchema $column): string|int|float|null => $this->mapper->mapToColumn($column, $entity->{$column->propertyName}),
                $this->schema->getInsertableColumns(),
            )));
        }

        return $values;
    }
}
