<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Query\Factory\DeleteFactory;
use MarekSkopal\ORM\Query\Factory\InsertFactory;
use MarekSkopal\ORM\Query\Factory\SelectFactory;
use MarekSkopal\ORM\Query\Factory\UpdateFactory;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

readonly class QueryProvider
{
    private SelectFactory $selectFactory;

    private InsertFactory $insertFactory;

    private UpdateFactory $updateFactory;

    private DeleteFactory $deleteFactory;

    public function __construct(
        private DatabaseInterface $database,
        private EntityFactory $entityFactory,
        private SchemaProvider $schemaProvider,
        private Mapper $mapper,
    )
    {
        $this->selectFactory = new SelectFactory($this->database, $this->entityFactory, $this->schemaProvider);
        $this->insertFactory = new InsertFactory($this->database, $this->schemaProvider, $this->mapper);
        $this->updateFactory = new UpdateFactory($this->database, $this->schemaProvider, $this->mapper);
        $this->deleteFactory = new DeleteFactory($this->database, $this->schemaProvider);
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

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Insert<T>
     */
    public function insert(string $entityClass): Insert
    {
        return $this->insertFactory->create($entityClass);
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Update<T>
     */
    public function update(string $entityClass): Update
    {
        return $this->updateFactory->create($entityClass);
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Delete<T>
     */
    public function delete(string $entityClass): Delete
    {
        return $this->deleteFactory->create($entityClass);
    }
}
