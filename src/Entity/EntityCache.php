<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Entity;

class EntityCache
{
    /** @var array<class-string, array<int, object>> */
    private array $entities = [];

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @param int $id
     * @return T|null
     */
    public function getEntity(string $entityClass, int $id): ?object
    {
        /** @var T|null $entity */
        $entity = $this->entities[$entityClass][$id] ?? null;
        return $entity;
    }

    public function addEntity(object $entity, int $id): void
    {
        $this->entities[$entity::class][$id] = $entity;
    }

    public function clear(): void
    {
        $this->entities = [];
    }
}
