<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Mapper;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use MarekSkopal\ORM\Relation\RelationResolver;

/**
 * Collection of related entities.
 *
 * Implemented as an IteratorAggregate rather than an Iterator so every foreach gets its own
 * cursor: nested loops over the same instance and re-iteration after a partial loop both work.
 *
 * A collection loaded from the database is lazy: its items are fetched on first access. It keeps
 * only a reference to the relation resolver and its owner's id, which is much smaller than a
 * per-collection closure.
 *
 * @template T of object
 * @implements IteratorAggregate<int|string, T>
 * @implements ArrayAccess<int|string, T>
 */
class Collection implements IteratorAggregate, ArrayAccess, Countable
{
    /** @var array<T>|null null until a lazy collection is loaded */
    private ?array $items;

    private ?RelationResolver $resolver = null;

    private string $relationKey = '';

    private int|string $ownerId = 0;

    /** @param array<T> $items */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Creates a collection that loads its items through the resolver on first access.
     *
     * @internal used by RelationResolver
     * @return self<object>
     */
    public static function lazy(RelationResolver $resolver, string $relationKey, int|string $ownerId): self
    {
        $collection = new self();
        $collection->items = null;
        $collection->resolver = $resolver;
        $collection->relationKey = $relationKey;
        $collection->ownerId = $ownerId;

        return $collection;
    }

    /** Whether the items are in memory; false for a lazy collection that was never accessed. */
    public function isInitialized(): bool
    {
        return $this->items !== null;
    }

    /** @return ArrayIterator<int|string, T> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items ?? $this->load());
    }

    /** @return array<T> */
    public function toArray(): array
    {
        return $this->items ?? $this->load();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset(($this->items ?? $this->load())[$offset]);
    }

    /** @return T */
    public function offsetGet(mixed $offset): object
    {
        return ($this->items ?? $this->load())[$offset];
    }

    /** @param T $value */
    public function offsetSet(mixed $offset, $value): void
    {
        $this->items ?? $this->load();

        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->items ?? $this->load();

        unset($this->items[$offset]);
    }

    public function count(): int
    {
        return count($this->items ?? $this->load());
    }

    /** @return array<T> */
    private function load(): array
    {
        $resolver = $this->resolver ?? throw new \LogicException('Lazy collection has no resolver.');

        /** @var array<T> $items the resolver loads entities of the relation's target class */
        $items = $resolver->loadCollection($this->relationKey, $this->ownerId);
        $this->items = $items;
        $this->resolver = null;

        return $items;
    }
}
