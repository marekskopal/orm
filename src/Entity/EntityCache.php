<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Entity;

use Closure;

class EntityCache
{
    /** @var array<class-string, array<int|string, object>> */
    private array $entities = [];

    /** @var list<Closure(): void> */
    private array $clearListeners = [];

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return T|null
     */
    public function getEntity(string $entityClass, int|string $id): ?object
    {
        /** @var T|null $entity */
        $entity = $this->entities[$entityClass][$id] ?? null;
        return $entity;
    }

    public function addEntity(object $entity, int|string $id): void
    {
        $this->entities[$entity::class][$id] = $entity;
    }

    /**
     * Returns the identity map by reference, so the relation resolver can read and write it on the
     * hydration hot path without a method call per row.
     *
     * @internal used by RelationResolver
     * @return array<class-string, array<int|string, object>>
     */
    public function &getIdentityMap(): array
    {
        return $this->entities;
    }

    /** Runs $listener whenever the cache is cleared, so state tied to cached entities is dropped too. */
    public function onClear(Closure $listener): void
    {
        $this->clearListeners[] = $listener;
    }

    public function clear(): void
    {
        $this->entities = [];

        foreach ($this->clearListeners as $listener) {
            $listener();
        }
    }
}
