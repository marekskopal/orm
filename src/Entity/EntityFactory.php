<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Entity;

use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Schema\ColumnSchema;
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
     * @param array<string, float|int|string|bool|null> $values
     * @return T
     */
    public function create(string $entityClass, array $values): object
    {
        $entitySchema = $this->schemaProvider->getEntitySchema($entityClass);
        $primaryColumnName = $entitySchema->getPrimaryColumn()->columnName;

        /** @var int $primaryValue */
        $primaryValue = $values[$primaryColumnName];

        $entity = $this->entityCache->getEntity($entityClass, $primaryValue);
        if ($entity !== null) {
            return $entity;
        }

        $constructorParameters = $this->entityReflection->getConstructorParameters($entityClass);

        $properties = [];
        foreach ($constructorParameters as $parameter) {
            $columnSchema = $entitySchema->columns[$parameter->getName()];

            $properties[] = $this->mapper->mapToProperty(
                $entitySchema,
                $columnSchema,
                $this->columnValue($values, $columnSchema, $primaryColumnName),
            );
        }

        $entity = new $entityClass(...$properties);

        $propertiesNotInConstructor = $this->entityReflection->getPropertiesNotInConstructor($entityClass);
        foreach ($propertiesNotInConstructor as $property) {
            $columnSchema = $entitySchema->columns[$property->getName()];

            // @phpstan-ignore-next-line property.dynamicName
            $entity->{$property->getName()} = $this->mapper->mapToProperty(
                $entitySchema,
                $columnSchema,
                $this->columnValue($values, $columnSchema, $primaryColumnName),
            );
        }

        $this->entityCache->addEntity($entity, $primaryValue);

        return $entity;
    }

    /**
     * Picks the raw database value for a column. Virtual (collection / inverse) relations are
     * keyed by the entity's own primary key. Native booleans, which pdo_pgsql returns for
     * BOOLEAN columns, are normalised to int so every driver hands the mapper the same types.
     *
     * @param array<string, float|int|string|bool|null> $values
     */
    private function columnValue(array $values, ColumnSchema $columnSchema, string $primaryColumnName): float|int|string|null
    {
        $value = $this->isVirtualRelation($columnSchema->relationType)
            ? $values[$primaryColumnName]
            : $values[$columnSchema->columnName] ?? null;

        return is_bool($value) ? (int) $value : $value;
    }

    private function isVirtualRelation(?RelationEnum $relationType): bool
    {
        return $relationType === RelationEnum::OneToMany
            || $relationType === RelationEnum::OneToOneInverse
            || $relationType === RelationEnum::ManyToMany
            || $relationType === RelationEnum::ManyToManyInverse;
    }
}
