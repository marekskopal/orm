<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Entity;

use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Schema\Schema;

class EntityFactory
{
    public function __construct(
        private readonly Schema $schema,
        private readonly EntityCache $entityCache,
        private readonly EntityReflection $entityReflection,
    ) {
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @param array<string, float|int|string> $values
     * @return T
     */
    public function create(string $entityClass, array $values, Mapper $mapper): object
    {
        $entity = $this->entityCache->getEntity($entityClass, $values['id']);
        if ($entity !== null) {
            return $entity;
        }

        $entitySchema = $this->schema->entities[$entityClass];

        $parameters = $this->entityReflection->getParameters($entityClass);

        $properties = [];
        foreach ($parameters as $parameter) {
            $columnSchema = $entitySchema->columns[$parameter->getName()];
            $properties[] = $mapper->mapColumn($columnSchema, $values[$columnSchema->columnName]);
        }

        $entity = new $entityClass(...$properties);
        $this->entityCache->addEntity($entity);

        return $entity;
    }
}
