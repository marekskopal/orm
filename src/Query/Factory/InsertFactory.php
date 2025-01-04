<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query\Factory;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Query\Insert;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

readonly class InsertFactory
{
    public function __construct(private DatabaseInterface $database, private SchemaProvider $schemaProvider, private Mapper $mapper)
    {
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Insert<T>
     */
    public function create(string $entityClass): Insert
    {
        return new Insert($this->database->getPdo(), $entityClass, $this->schemaProvider->getEntitySchema($entityClass), $this->mapper);
    }
}
