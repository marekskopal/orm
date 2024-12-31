<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Mapper;

use ArrayAccess;
use Countable;
use Iterator;

/**
 * @template T of object
 * @implements Iterator<T>
 * @implements ArrayAccess<int|string, T>
 */
class Collection implements Iterator, ArrayAccess, Countable
{
    /** @param array<T> $items */
    public function __construct(private array $items = [])
    {
    }

    /**
     * @return T|false $item
     * @phpstan-ignore-next-line method.childReturnType
     */
    public function current(): object|false
    {
        return current($this->items);
    }

    public function next(): void
    {
        next($this->items);
    }

    public function key(): int|string|null
    {
        return key($this->items);
    }

    public function valid(): bool
    {
        return key($this->items) !== null;
    }

    public function rewind(): void
    {
        reset($this->items);
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
