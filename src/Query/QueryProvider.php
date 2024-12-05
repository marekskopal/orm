<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

readonly class QueryProvider
{
    private SelectFactory $selectFactory;

    public function __construct(
        private DatabaseInterface $database,
        private EntityFactory $entityFactory,
        private SchemaProvider $schemaProvider,
    )
    {
        $this->selectFactory = new SelectFactory($this->database, $this->entityFactory, $this->schemaProvider);
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Select<T>
     */
    public function select(string $entityClass): Select
    {
        return $this->selectFactory->create($entityClass);
    }
}
