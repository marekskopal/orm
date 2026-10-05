<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Exception\ExceptionFactory;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use PDOStatement;

/** @template T of object */
class Update extends AbstractQuery
{
    /** @var T */
    private object $entity;

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
            $this->escape($this->schema->table),
            'SET',
            $this->getSetQuery(),
            $this->getWhereQuery(),
        ]);
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

    private function getSetQuery(): string
    {
        return implode(',', array_map(
            fn(ColumnSchema $column): string => $this->escape($column->columnName) . '=?',
            $this->schema->getInsertableColumns(),
        ));
    }

    private function getWhereQuery(): string
    {
        $primaryColumnSchema = $this->schema->getPrimaryColumn();

        return 'WHERE ' . $this->escape($primaryColumnSchema->columnName) . '=?';
    }

    /** @return list<string|int|float|null> */
    private function getValues(): array
    {
        $primaryColumnSchema = $this->schema->getPrimaryColumn();

        $values = array_values($this->schemaProvider->extract($this->entity));
        // @phpstan-ignore-next-line property.dynamicName
        $values[] = (int) $this->entity->{$primaryColumnSchema->propertyName};

        return $values;
    }
}
