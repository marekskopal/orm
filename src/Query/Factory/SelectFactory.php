<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query\Factory;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

readonly class SelectFactory
{
    public function __construct(
        private DatabaseInterface $database,
        private EntityFactory $entityFactory,
        private SchemaProvider $schemaProvider,
    )
    {
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Select<T>
     */
    public function create(string $entityClass): Select
    {
        return new Select(
            $this->database->getPdo(),
            $this->entityFactory,
            $entityClass,
            $this->schemaProvider,
        );
    }
}
