<?php

declare(strict_types=1);

namespace MarekSkopal\ORM;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Entity\EntityCache;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Entity\EntityReflection;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Repository\RepositoryInterface;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Schema\Schema;

readonly class ORM
{
    private SchemaProvider $schemaProvider;

    private QueryProvider $queryProvider;

    private EntityCache $entityCache;

    private EntityReflection $entityReflection;

    private EntityFactory $entityFactory;

    private Mapper $mapper;

    public function __construct(private DatabaseInterface $database, private Schema $schema)
    {
        $this->schemaProvider = new SchemaProvider($this->schema);
        $this->entityCache = new EntityCache();
        $this->entityReflection = new EntityReflection();
        $this->entityFactory = new EntityFactory($this->schemaProvider, $this->entityCache, $this->entityReflection);
        $this->mapper = new Mapper($this->schemaProvider, $this->entityCache);
        $this->queryProvider = new QueryProvider($this->database, $this->entityFactory, $this->schemaProvider, $this->mapper);
        $this->mapper->setQueryProvider($this->queryProvider);
        $this->entityFactory->setMapper($this->mapper);
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return RepositoryInterface<T>
     */
    public function getRepository(string $entityClass): RepositoryInterface
    {
        $repositoryClass = $this->schema->entities[$entityClass]->repositoryClass;

        //@phpstan-ignore-next-line return.type
        return new $repositoryClass($entityClass, $this->queryProvider, $this->schemaProvider);
    }

    public function getQueryProvider(): QueryProvider
    {
        return $this->queryProvider;
    }

    public function getEntityCache(): EntityCache
    {
        return $this->entityCache;
    }
}
