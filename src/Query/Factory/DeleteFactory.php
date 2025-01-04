<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query\Factory;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Query\Delete;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

readonly class DeleteFactory
{
    public function __construct(private DatabaseInterface $database, private SchemaProvider $schemaProvider)
    {
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Delete<T>
     */
    public function create(string $entityClass): Delete
    {
        return new Delete(
            $this->database->getPdo(),
            $entityClass,
            $this->schemaProvider->getEntitySchema($entityClass),
            $this->schemaProvider->getPrimaryColumnSchema($entityClass),
        );
    }
}
