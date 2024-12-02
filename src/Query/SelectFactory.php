<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Schema\Schema;

readonly class SelectFactory
{
    public function __construct(private DatabaseInterface $database, private Schema $schema)
    {
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     */
    public function create(string $entityClass): Select
    {
        $entitySchema = $this->schema->entities[$entityClass];

        return new Select($this->database->getPdo(), $entitySchema);
    }
}
