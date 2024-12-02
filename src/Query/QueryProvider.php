<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Schema\Schema;

readonly class QueryProvider
{
    private readonly SelectFactory $selectFactory;

    public function __construct(private DatabaseInterface $database, private Schema $schema)
    {
        $this->selectFactory = new SelectFactory($this->database, $this->schema);
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
