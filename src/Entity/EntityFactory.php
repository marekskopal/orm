<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Entity;

use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

class EntityFactory
{
    public function __construct(
        private readonly SchemaProvider $schemaProvider,
        private readonly EntityCache $entityCache,
        private readonly EntityReflection $entityReflection,
        private readonly Mapper $mapper,
    ) {
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @param array<string, float|int|string|null> $values
     * @return T
     */
    public function create(string $entityClass, array $values): object
    {
        /** @var int $primaryValue */
        $primaryValue = $values[$this->schemaProvider->getPrimaryColumnSchema($entityClass)->columnName];

        $entity = $this->entityCache->getEntity($entityClass, $primaryValue);
        if ($entity !== null) {
            return $entity;
        }

        $entitySchema = $this->schemaProvider->getEntitySchema($entityClass);

        $constructorParameters = $this->entityReflection->getConstructorParameters($entityClass);

        $properties = [];
        foreach ($constructorParameters as $parameter) {
            $columnSchema = $entitySchema->columns[$parameter->getName()];
            $value = $this->isVirtualRelation($columnSchema->relationType) ? $values[$entitySchema->getPrimaryColumn()->columnName] : $values[$columnSchema->columnName] ?? null;

            $properties[] = $this->mapper->mapToProperty($entitySchema, $columnSchema, $value);
        }

        $entity = new $entityClass(...$properties);

        $propertiesNotInConstructor = $this->entityReflection->getPropertiesNotInConstructor($entityClass);
        foreach ($propertiesNotInConstructor as $property) {
            $columnSchema = $entitySchema->columns[$property->getName()];
            $value = $this->isVirtualRelation($columnSchema->relationType) ? $values[$entitySchema->getPrimaryColumn()->columnName] : $values[$columnSchema->columnName] ?? null;

            // @phpstan-ignore-next-line property.dynamicName
            $entity->{$property->getName()} = $this->mapper->mapToProperty($entitySchema, $columnSchema, $value);
        }

        $this->entityCache->addEntity($entity, $primaryValue);

        return $entity;
    }

    private function isVirtualRelation(?RelationEnum $relationType): bool
    {
        return $relationType === RelationEnum::OneToMany
            || $relationType === RelationEnum::OneToOneInverse
            || $relationType === RelationEnum::ManyToMany
            || $relationType === RelationEnum::ManyToManyInverse;
    }
}
