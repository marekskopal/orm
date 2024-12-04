<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Mapper;

use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use Ramsey\Uuid\Uuid;

readonly class Mapper
{
    public function __construct(private QueryProvider $queryProvider, private EntityFactory $entityFactory,)
    {
    }

    public function mapColumn(ColumnSchema $schema, string|int|float $value): string|int|float|bool|object
    {
        return match ($schema->propertyType) {
            PropertyTypeEnum::String => (string) $value,
            PropertyTypeEnum::Int => (int) $value,
            PropertyTypeEnum::Float => (float) $value,
            PropertyTypeEnum::Bool => (bool) $value,
            PropertyTypeEnum::Uuid => Uuid::fromString((string) $value),
            PropertyTypeEnum::Relation => $this->mapRelation($schema, (int) $value),
        };
    }

    private function mapRelation(ColumnSchema $schema, int $value): object
    {
        return match ($schema->relationType) {
            RelationEnum::ManyToOne => $this->mapRelationManyToOne($schema->relationEntityClass, $value),
            default => throw new \RuntimeException('Relation type not found'),
        };
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     */
    private function mapRelationManyToOne(string $entityClass, int $value): object
    {
        $result = $this->queryProvider->select($entityClass)->where([['id', '=', $value]])->fetch();
        if ($result === null) {
            throw new \RuntimeException(sprintf('Entity "%s" with id "%d" not found', $entityClass, $value));
        }

        return $this->entityFactory->create($entityClass, $result, $this);
    }
}
