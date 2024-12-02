<?php

declare(strict_types=1);

namespace MarekSkopal\ORM;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Factory\EntityFactory;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Repository\RepositoryInterface;
use MarekSkopal\ORM\Schema\Schema;

readonly class ORM
{
    private QueryProvider $queryProvider;

    private EntityFactory $entityFactory;

    public function __construct(private DatabaseInterface $database, private Schema $schema)
    {
        $this->queryProvider = new QueryProvider($this->database, $this->schema);
        $this->entityFactory = new EntityFactory($this->schema, new Mapper());
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return RepositoryInterface<T>
     */
    public function getRepository(string $entityClass): RepositoryInterface
    {
        $repositoryClass = $this->schema->entities[$entityClass]->repositoryClass;

        return new $repositoryClass($entityClass, $this->queryProvider, $this->entityFactory);
    }
}
