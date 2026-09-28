<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Mapper;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Collection of related entities.
 *
 * Implemented as an IteratorAggregate rather than an Iterator so every foreach gets its own
 * cursor: nested loops over the same instance and re-iteration after a partial loop both work.
 *
 * @template T of object
 * @implements IteratorAggregate<int|string, T>
 * @implements ArrayAccess<int|string, T>
 */
class Collection implements IteratorAggregate, ArrayAccess, Countable
{
    /** @param array<T> $items */
    public function __construct(private array $items = [])
    {
    }

    /** @return ArrayIterator<int|string, T> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /** @return array<T> */
    public function toArray(): array
    {
        return $this->items;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /** @return T */
    public function offsetGet(mixed $offset): object
    {
        return $this->items[$offset];
    }

    /** @param T $value */
    public function offsetSet(mixed $offset, $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
