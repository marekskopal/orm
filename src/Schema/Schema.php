<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema;

readonly class Schema
{
    /**
     * @template T of object
     * @param array<class-string<T>, EntitySchema> $entities
     */
    public function __construct(public array $entities)
    {
    }
}
