<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Repository;

use Iterator;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Query\Select;

/**
 * @template T of object
 * @implements RepositoryInterface<T>
 */
abstract class AbstractRepository implements RepositoryInterface
{
    /** @param class-string<T> $entityClass */
    public function __construct(private readonly string $entityClass, private readonly QueryProvider $queryProvider,)
    {
    }

    /** @return Select<T> */
    public function select(): Select
    {
        return $this->queryProvider->select($this->entityClass);
    }

    /**
     * @param array<scalar|array{0: string, 1: string, 2: scalar}> $where
     * @return Iterator<T>
     */
    public function find(array $where = []): Iterator
    {
        return $this->select()->where($where)->fetchAll();
    }

    /**
     * @param array<scalar|array{0: string, 1: string, 2: scalar}> $where
     * @return T|null
     */
    public function findOne(array $where = []): ?object
    {
        return $this->select()->where($where)->fetch();
    }
}
