<?php

declare(strict_types=1);

namespace MarekSkopal\ORM;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Entity\IdentityMap;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Relation\RelationResolver;
use MarekSkopal\ORM\Repository\RepositoryInterface;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Transaction\TransactionProvider;
use MarekSkopal\ORM\UnitOfWork\UnitOfWork;

readonly class ORM
{
    private SchemaProvider $schemaProvider;

    private QueryProvider $queryProvider;

    private IdentityMap $identityMap;

    private RelationResolver $relationResolver;

    private TransactionProvider $transactionProvider;

    private UnitOfWork $unitOfWork;

    public function __construct(private DatabaseInterface $database, private Schema $schema)
    {
        $this->schemaProvider = new SchemaProvider($this->schema);
        $this->identityMap = new IdentityMap();
        $this->relationResolver = new RelationResolver($this->database, $this->schemaProvider, $this->identityMap);
        $this->queryProvider = new QueryProvider($this->database, $this->relationResolver, $this->schemaProvider);
        $this->transactionProvider = new TransactionProvider($this->database);
        $this->unitOfWork = new UnitOfWork(
            $this->database,
            $this->schemaProvider,
            $this->identityMap,
            $this->queryProvider,
            $this->relationResolver,
        );
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
        return new $repositoryClass($entityClass, $this->queryProvider, $this->schemaProvider, $this->unitOfWork);
    }

    public function getQueryProvider(): QueryProvider
    {
        return $this->queryProvider;
    }

    public function getIdentityMap(): IdentityMap
    {
        return $this->identityMap;
    }

    /** The unit of work for deferred writes: persist() and remove() schedule, flush() writes. */
    public function getUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    public function getTransactionProvider(): TransactionProvider
    {
        return $this->transactionProvider;
    }
}
