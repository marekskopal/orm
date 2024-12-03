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
        return $this->entities[$entityClass][$id] ?? null;
    }

    /** @param object{id: int} $entity */
    public function addEntity(object $entity): void
    {
        $this->entities[$entity::class][$entity->id] = $entity;
    }

    public function clear(): void
    {
        $this->entities = [];
    }
}
