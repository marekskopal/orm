<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Entity;

use BackedEnum;
use Closure;
use Stringable;

/**
 * Tracks the one instance per entity class and primary key, and a snapshot of each managed
 * entity's column values as last read from or written to the database.
 *
 * Keys are normalised: integers stay integers, everything else (UUIDs, manual string keys) becomes
 * a string, so a key read from a row and the same key held by an entity property match.
 *
 * Snapshots are keyed by object id. That is safe because every entity with a snapshot is registered
 * here, which keeps it alive: an id cannot be reused while its snapshot exists. Snapshots go when the
 * entity is removed or the map is cleared. (A WeakMap would add cycle-collector work for every
 * hydrated entity, and its weak keys buy nothing while the map holds the entities strongly.)
 */
class IdentityMap
{
    /** @var array<class-string, array<int|string, object>> */
    private array $entities = [];

    /** @var array<int, list<mixed>|array<string, string|int|float|null>> object id => snapshot */
    private array $snapshots = [];

    /** @var list<Closure(): void> */
    private array $clearListeners = [];

    public static function key(mixed $id): int|string
    {
        return match (true) {
            is_int($id), is_string($id) => $id,
            $id instanceof BackedEnum => $id->value,
            $id instanceof Stringable, is_float($id) => (string) $id,
            default => throw new \InvalidArgumentException(
                sprintf('Cannot use a value of type "%s" as an entity key.', get_debug_type($id)),
            ),
        };
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return T|null
     */
    public function get(string $entityClass, mixed $id): ?object
    {
        /** @var T|null $entity */
        $entity = $this->entities[$entityClass][self::key($id)] ?? null;
        return $entity;
    }

    public function add(object $entity, mixed $id): void
    {
        $this->entities[$entity::class][self::key($id)] = $entity;
    }

    /** Whether this exact instance is the one registered for its class and id. */
    public function contains(object $entity, mixed $id): bool
    {
        return ($this->entities[$entity::class][self::key($id)] ?? null) === $entity;
    }

    /** Forgets an entity: its registration (if it is the registered instance) and its snapshot. */
    public function remove(object $entity, mixed $id): void
    {
        $key = self::key($id);
        if (($this->entities[$entity::class][$key] ?? null) === $entity) {
            unset($this->entities[$entity::class][$key]);
        }

        unset($this->snapshots[spl_object_id($entity)]);
    }

    /** @return list<mixed>|array<string, string|int|float|null>|null */
    public function getSnapshot(object $entity): ?array
    {
        return $this->snapshots[spl_object_id($entity)] ?? null;
    }

    /** @param list<mixed>|array<string, string|int|float|null> $snapshot */
    public function setSnapshot(object $entity, array $snapshot): void
    {
        $this->snapshots[spl_object_id($entity)] = $snapshot;
    }

    public function hasSnapshot(object $entity): bool
    {
        return isset($this->snapshots[spl_object_id($entity)]);
    }

    /**
     * Returns the entity map by reference, so the relation resolver can read and write it on the
     * hydration hot path without a method call per row.
     *
     * @internal used by RelationResolver
     * @return array<class-string, array<int|string, object>>
     */
    public function &getEntitiesReference(): array
    {
        return $this->entities;
    }

    /**
     * Returns the snapshots by reference, so generated hydrators record one without a method call.
     *
     * @internal used by RelationResolver
     * @return array<int, list<mixed>|array<string, string|int|float|null>>
     */
    public function &getSnapshotsReference(): array
    {
        return $this->snapshots;
    }

    /** Moves a snapshot to another object, e.g. from the instance behind a proxy to the proxy. */
    public function moveSnapshot(object $from, object $to): void
    {
        $fromId = spl_object_id($from);
        if (!isset($this->snapshots[$fromId])) {
            return;
        }

        $this->snapshots[spl_object_id($to)] = $this->snapshots[$fromId];
        unset($this->snapshots[$fromId]);
    }

    /** Runs $listener whenever the map is cleared, so state tied to managed entities is dropped too. */
    public function onClear(Closure $listener): void
    {
        $this->clearListeners[] = $listener;
    }

    public function clear(): void
    {
        $this->entities = [];
        $this->snapshots = [];

        foreach ($this->clearListeners as $listener) {
            $listener();
        }
    }
}
