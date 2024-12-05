<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

readonly class QueryProvider
{
    private SelectFactory $selectFactory;

    public function __construct(private DatabaseInterface $database, private SchemaProvider $schemaProvider)
    {
        $this->selectFactory = new SelectFactory($this->database, $this->schemaProvider);
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     */
    public function select(string $entityClass): Select
    {
        return $this->selectFactory->create($entityClass);
    }
}
