<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Mapper;

use Iterator;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use Ramsey\Uuid\Uuid;

class Mapper
{
    private QueryProvider $queryProvider;

    public function __construct(private readonly SchemaProvider $schemaProvider)
    {
    }

    public function setQueryProvider(QueryProvider $queryProvider): void
    {
        $this->queryProvider = $queryProvider;
    }

    public function mapToProperty(
        EntitySchema $entitySchema,
        ColumnSchema $columnSchema,
        string|int|float $value,
    ): string|int|float|bool|object
    {
        return match ($columnSchema->propertyType) {
            PropertyTypeEnum::String => (string) $value,
            PropertyTypeEnum::Int => (int) $value,
            PropertyTypeEnum::Float => (float) $value,
            PropertyTypeEnum::Bool => (bool) $value,
            PropertyTypeEnum::Uuid => Uuid::fromString((string) $value),
            PropertyTypeEnum::Relation => $this->mapRelationToProperty($entitySchema, $columnSchema, (int) $value),
        };
    }

    public function mapToColumn(ColumnSchema $columnSchema, string|int|float|bool|object $value,): string|int|float
    {
        return match ($columnSchema->propertyType) {
            PropertyTypeEnum::String => (string) $value,
            PropertyTypeEnum::Int => (int) $value,
            PropertyTypeEnum::Float => (float) $value,
            PropertyTypeEnum::Bool => (int) $value,
            PropertyTypeEnum::Uuid => (string) $value,
            PropertyTypeEnum::Relation => $this->mapRelationToColumn($columnSchema, $value),
        };
    }

    private function mapRelationToProperty(EntitySchema $entitySchema, ColumnSchema $columnSchema, int $value): object
    {
        $relationEntityClass = $columnSchema->relationEntityClass ?? throw new \RuntimeException('Relation entity class not found');

        return match ($columnSchema->relationType) {
            RelationEnum::OneToMany => $this->mapRelationOneToManyToProperty($entitySchema->table, $relationEntityClass, $value),
            RelationEnum::ManyToOne => $this->mapRelationManyToOneToProperty($relationEntityClass, $value),
            default => throw new \RuntimeException('Relation type not found'),
        };
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Iterator<T>
     */
    private function mapRelationOneToManyToProperty(string $table, string $entityClass, int $value): Iterator
    {
        return $this->queryProvider->select($entityClass)->where([[$table . '_id', '=', $value]])->fetchAll();
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return T
     */
    private function mapRelationManyToOneToProperty(string $entityClass, int $value): object
    {
        $primaryColumnSchema = $this->schemaProvider->getPrimaryColumnSchema($entityClass);

        $entity = $this->queryProvider->select($entityClass)->where([[$primaryColumnSchema->columnName, '=', $value]])->fetch();
        if ($entity === null) {
            throw new \RuntimeException(sprintf('Entity "%s" with id "%d" not found', $entityClass, $value));
        }

        return $entity;
    }

    private function mapRelationToColumn(ColumnSchema $columnSchema, object $value): int
    {
        $relationEntityClass = $columnSchema->relationEntityClass ?? throw new \RuntimeException('Relation entity class not found');

        return match ($columnSchema->relationType) {
            RelationEnum::ManyToOne => $this->mapRelationManyToOneToColumn($relationEntityClass, $value),
            default => throw new \RuntimeException('Relation type not found'),
        };
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     */
    private function mapRelationManyToOneToColumn(string $entityClass, object $value): int
    {
        $primaryColumnSchema = $this->schemaProvider->getPrimaryColumnSchema($entityClass);
        // @phpstan-ignore-next-line property.dynamicName
        return $value->{$primaryColumnSchema->columnName};
    }
}
