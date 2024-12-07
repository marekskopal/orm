<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Repository;

use MarekSkopal\ORM\Query\Select;

/** @template T of object */
interface RepositoryInterface
{
    /** @return Select<T> */
    public function select(): Select;

    /**
     * @param array<string,scalar>|array{0: string, 1: string, 2: scalar}|list<array{0: string, 1: string, 2: scalar}> $where
     * @return iterable<T>
     */
    public function find(array $where = []): iterable;

    /**
     * @param array<string,scalar>|array{0: string, 1: string, 2: scalar}|list<array{0: string, 1: string, 2: scalar}> $where
     * @return T|null
     */
    public function findOne(array $where = []): ?object;

    /** @param T $entity */
    public function persist(object $entity): void;

    /** @param T $entity */
    public function delete(object $entity): void;
}
