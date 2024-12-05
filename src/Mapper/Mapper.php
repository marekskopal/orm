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

readonly class Mapper
{
    public function __construct(private SchemaProvider $schemaProvider, private QueryProvider $queryProvider,)
    {
    }

    public function mapColumn(
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
            PropertyTypeEnum::Relation => $this->mapRelation($entitySchema, $columnSchema, (int) $value),
        };
    }

    private function mapRelation(EntitySchema $entitySchema, ColumnSchema $columnSchema, int $value): object
    {
        $relationEntityClass = $columnSchema->relationEntityClass ?? throw new \RuntimeException('Relation entity class not found');

        return match ($columnSchema->relationType) {
            RelationEnum::OneToMany => $this->mapRelationOneToMany($entitySchema->table, $relationEntityClass, $value),
            RelationEnum::ManyToOne => $this->mapRelationManyToOne($relationEntityClass, $value),
            default => throw new \RuntimeException('Relation type not found'),
        };
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Iterator<T>
     */
    private function mapRelationOneToMany(string $table, string $entityClass, int $value): Iterator
    {
        return $this->queryProvider->select($entityClass)->where([[$table . '_id', '=', $value]])->fetchAll();
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return T
     */
    private function mapRelationManyToOne(string $entityClass, int $value): object
    {
        $primaryColumnSchema = $this->schemaProvider->getPrimaryColumnSchema($entityClass);

        $entity = $this->queryProvider->select($entityClass)->where([[$primaryColumnSchema->columnName, '=', $value]])->fetch();
        if ($entity === null) {
            throw new \RuntimeException(sprintf('Entity "%s" with id "%d" not found', $entityClass, $value));
        }

        return $entity;
    }
}
