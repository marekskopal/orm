<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Repository;

use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Query\Select;

/**
 * @template T of object
 * @implements RepositoryInterface<T>
 */
abstract class AbstractRepository implements RepositoryInterface
{
    /** @param class-string<T> $entityClass */
    public function __construct(
        private readonly string $entityClass,
        private readonly QueryProvider $queryProvider,
        private readonly EntityFactory $entityFactory,
        private readonly Mapper $mapper,
    ) {
    }

    public function select(): Select
    {
        return $this->queryProvider->select($this->entityClass);
    }

    /**
     * @param array<scalar|array{0: string, 1: string, 2: scalar}> $where
     * @return iterable<T>
     */
    public function find(array $where = []): iterable
    {
        $results = $this->select()->where($where)->fetchAll();
        foreach ($results as $result) {
            yield $this->entityFactory->create($this->entityClass, $result, $this->mapper);
        }
    }

    /**
     * @param array<scalar|array{0: string, 1: string, 2: scalar}> $where
     * @return T|null
     */
    public function findOne(array $where = []): ?object
    {
        $result = $this->select()->where($where)->fetch();
        return $result !== null ? $this->entityFactory->create($this->entityClass, $result, $this->mapper) : null;
    }
}
