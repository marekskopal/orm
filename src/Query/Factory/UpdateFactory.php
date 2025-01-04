<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query\Factory;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Query\Update;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

readonly class UpdateFactory
{
    public function __construct(private DatabaseInterface $database, private SchemaProvider $schemaProvider, private Mapper $mapper)
    {
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Update<T>
     */
    public function create(string $entityClass): Update
    {
        return new Update($this->database->getPdo(), $entityClass, $this->schemaProvider->getEntitySchema($entityClass), $this->mapper);
    }
}
