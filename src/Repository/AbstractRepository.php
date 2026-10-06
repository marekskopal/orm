<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Repository;

use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Query\Where\WhereBuilder;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\UnitOfWork\UnitOfWork;

/**
 * @template T of object
 * @implements RepositoryInterface<T>
 * @phpstan-import-type Where from WhereBuilder
 */
abstract class AbstractRepository implements RepositoryInterface
{
    /** @param class-string<T> $entityClass */
    public function __construct(
        protected readonly string $entityClass,
        protected readonly QueryProvider $queryProvider,
        protected readonly SchemaProvider $schemaProvider,
        protected readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @return Select<T> */
    public function select(): Select
    {
        return $this->queryProvider->select($this->entityClass);
    }

    /**
     * @phpstan-impure
     * @param Where $where
     * @return list<T>
     */
    public function findAll(array|callable $where = []): array
    {
        return $this->select()->where($where)->fetchAll();
    }

    /**
     * @phpstan-impure
     * @param Where $where
     * @return T|null
     */
    public function findOne(array|callable $where = []): ?object
    {
        return $this->select()->where($where)->fetchOne();
    }

    /**
     * Inserts or updates the entity and its cascade-persist relations right away. Only changed
     * columns are written. This flushes the unit of work, including work scheduled on it directly.
     *
     * @param T $entity
     */
    public function persist(object $entity): void
    {
        $this->unitOfWork->persist($entity)->flush();
    }

    /**
     * Deletes the entity and its cascade-remove relations right away. This flushes the unit of
     * work, including work scheduled on it directly.
     *
     * @param T $entity
     */
    public function delete(object $entity): void
    {
        $this->unitOfWork->remove($entity)->flush();
    }
}
