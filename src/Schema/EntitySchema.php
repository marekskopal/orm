<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema;

use MarekSkopal\ORM\Repository\RepositoryInterface;

readonly class EntitySchema
{
    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @param class-string<RepositoryInterface<T>> $repositoryClass
     * @param array<string, ColumnSchema> $columns
     */
    public function __construct(public string $entityClass, public string $repositoryClass, public string $table, public array $columns,)
    {
    }
}
