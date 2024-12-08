<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Repository;

use MarekSkopal\ORM\Query\Select;

/**
 * @template T of object
 * @phpstan-import-type Where from Select
 */
interface RepositoryInterface
{
    /** @return Select<T> */
    public function select(): Select;

    /**
     * @param Where $where
     * @return iterable<T>
     */
    public function findAll(array $where = []): iterable;

    /**
     * @param Where $where
     * @return T|null
     */
    public function findOne(array $where = []): ?object;

    /** @param T $entity */
    public function persist(object $entity): void;

    /** @param T $entity */
    public function delete(object $entity): void;
}
