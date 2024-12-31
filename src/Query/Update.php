<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Exception\ExceptionFactory;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Utils\NameUtils;
use PDO;
use PDOStatement;

/** @template T of object */
class Update extends AbstractQuery
{
    /** @var T */
    private object $entity;

    /** @param class-string<T> $entityClass */
    public function __construct(PDO $pdo, string $entityClass, EntitySchema $schema, private readonly Mapper $mapper)
    {
        parent::__construct($pdo, $entityClass, $schema);
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
            NameUtils::escape($this->schema->table),
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
            throw new ExceptionFactory()->create($e, $sql);
        }
    }

    private function getSetQuery(): string
    {
        return implode(',', array_map(
            fn(ColumnSchema $column): string => NameUtils::escape($column->columnName) . '=:' . $column->propertyName,
            $this->schema->getInsertableColumns(),
        ));
    }

    private function getWhereQuery(): string
    {
        $primaryColumnSchema = $this->schema->getPrimaryColumn();

        return 'WHERE ' . NameUtils::escape($primaryColumnSchema->columnName) . '=:' . $primaryColumnSchema->propertyName;
    }

    /** @return array<string, string|int|float|null> */
    private function getValues(): array
    {
        return array_merge(
            // @phpstan-ignore-next-line property.dynamicName
            ['id' => (int) $this->entity->{$this->schema->getPrimaryColumn()->propertyName}],
            array_map(
                fn(ColumnSchema $column): string|int|float|null => $this->mapper->mapToColumn(
                    $column,
                    // @phpstan-ignore-next-line argument.type property.dynamicName
                    $this->entity->{$column->propertyName},
                ),
                $this->schema->getInsertableColumns(),
            ),
        );
    }
}
