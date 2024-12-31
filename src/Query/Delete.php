<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Exception\ExceptionFactory;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;
use PDOStatement;

/** @template T of object */
class Delete extends AbstractQuery
{
    /** @var list<T> */
    private array $entities = [];

    /** @param class-string<T> $entityClass */
    public function __construct(
        PDO $pdo,
        string $entityClass,
        EntitySchema $schema,
        private readonly ColumnSchema $primaryColumnSchema,
    ) {
        parent::__construct($pdo, $entityClass, $schema);
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
            $this->schema->table,
            $this->getWhereQuery(),
        ]);
    }

    private function query(): PDOStatement
    {
        try {
            $sql = $this->getSql();
            $pdoStatement = $this->pdo->prepare($sql);
            $pdoStatement->execute($this->getIds());
            return $pdoStatement;
        } catch (\PDOException $e) {
            throw new ExceptionFactory()->create($e, $sql);
        }
    }

    private function getWhereQuery(): string
    {
        return 'WHERE ' . $this->primaryColumnSchema->columnName . ' IN (' . implode(
            ',',
            array_map(fn($item): string => '?', $this->entities),
        ) . ')';
    }

    /** @return list<int> */
    private function getIds(): array
    {
        return array_map(
            // @phpstan-ignore-next-line return.type property.dynamicName
            fn($entity): int => $entity->{$this->primaryColumnSchema->columnName},
            $this->entities,
        );
    }
}
