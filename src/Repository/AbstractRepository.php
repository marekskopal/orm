<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Repository;

use Iterator;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

/**
 * @template T of object
 * @implements RepositoryInterface<T>
 * @phpstan-import-type Where from Select
 */
abstract class AbstractRepository implements RepositoryInterface
{
    /** @param class-string<T> $entityClass */
    public function __construct(
        protected readonly string $entityClass,
        protected readonly QueryProvider $queryProvider,
        protected readonly SchemaProvider $schemaProvider,
    ) {
    }

    /** @return Select<T> */
    public function select(): Select
    {
        return $this->queryProvider->select($this->entityClass);
    }

    /**
     * @param Where $where
     * @return Iterator<T>
     */
    public function findAll(array $where = []): Iterator
    {
        return $this->select()->where($where)->fetchAll();
    }

    /**
     * @param Where $where
     * @return T|null
     */
    public function findOne(array $where = []): ?object
    {
        return $this->select()->where($where)->fetchOne();
    }

    /** @param T $entity */
    public function persist(object $entity): void
    {
        $primaryColumnSchema = $this->schemaProvider->getPrimaryColumnSchema($entity::class);
        // @phpstan-ignore-next-line property.dynamicName
        if (!isset($entity->{$primaryColumnSchema->columnName})) {
            $this->queryProvider->insert($entity)->execute();
            return;
        }

        $this->queryProvider->update($entity)->execute();
    }

    /** @param T $entity */
    public function delete(object $entity): void
    {
        $this->queryProvider->delete($entity)->execute();
    }
}
