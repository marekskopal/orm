<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Factory;

use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Schema\Schema;
use ReflectionClass;

class EntityFactory
{
    public function __construct(private readonly Schema $schema, private readonly Mapper $mapper)
    {
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @param array<string, float|int|string> $values
     * @return T
     */
    public function create(string $entityClass, array $values): object
    {
        $entitySchema = $this->schema->entities[$entityClass];

        $reflectionClass = new ReflectionClass($entityClass);
        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            throw new \RuntimeException('Entity must have constructor');
        }

        $parameters = $constructor->getParameters();
        $properties = [];
        foreach ($parameters as $parameter) {
            $columnSchema = $entitySchema->columns[$parameter->getName()];
            $properties[] = $this->mapper->mapColumn($columnSchema, $values[$columnSchema->columnName]);
        }
        return new $entityClass(...$properties);
    }
}
