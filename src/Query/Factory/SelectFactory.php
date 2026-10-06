<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query\Factory;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Relation\RelationResolver;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

readonly class SelectFactory
{
    public function __construct(
        private DatabaseInterface $database,
        private RelationResolver $relationResolver,
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
            $this->database,
            $entityClass,
            $this->schemaProvider->getEntitySchema($entityClass),
            $this->relationResolver,
            $this->schemaProvider,
        );
    }
}
