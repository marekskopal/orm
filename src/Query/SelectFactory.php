<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

readonly class SelectFactory
{
    public function __construct(private DatabaseInterface $database, private SchemaProvider $schemaProvider)
    {
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     */
    public function create(string $entityClass): Select
    {
        return new Select($this->database->getPdo(), $this->schemaProvider->getEntitySchema($entityClass));
    }
}
