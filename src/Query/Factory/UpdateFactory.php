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
     * @param T $entity
     * @return Update<T>
     */
    public function create(object $entity): Update
    {
        return new Update($this->database->getPdo(), $this->schemaProvider->getEntitySchema($entity::class), $this->mapper)->entity(
            $entity,
        );
    }
}
