<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Mapper;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Iterator;
use MarekSkopal\ORM\Entity\EntityCache;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Utils\ValidationUtils;
use Ramsey\Uuid\Uuid;
use ReflectionClass;

class Mapper implements MapperInterface
{
    private QueryProvider $queryProvider;

    private readonly ExtensionMapperProvider $extensionMapperProvider;

    public function __construct(private readonly SchemaProvider $schemaProvider, private readonly EntityCache $entityCache)
    {
        $this->extensionMapperProvider = new ExtensionMapperProvider();
    }

    public function setQueryProvider(QueryProvider $queryProvider): void
    {
        $this->queryProvider = $queryProvider;
    }

    public function mapToProperty(
        EntitySchema $entitySchema,
        ColumnSchema $columnSchema,
        string|int|float|null $value,
    ): string|int|float|bool|object|null
    {
        if (
            $value === null
            && (
                $columnSchema->propertyType !== PropertyTypeEnum::Relation
                || $columnSchema->relationType === RelationEnum::ManyToOne
            )
        ) {
            if (!$columnSchema->isNullable) {
                throw new \RuntimeException(sprintf('Column "%s" is not nullable', $columnSchema->columnName));
            }

            return null;
        }

        return match ($columnSchema->propertyType) {
            PropertyTypeEnum::String => (string) $value,
            PropertyTypeEnum::Int => (int) $value,
            PropertyTypeEnum::Float => (float) $value,
            PropertyTypeEnum::Bool => (bool) $value,
            PropertyTypeEnum::Uuid => Uuid::fromString((string) $value),
            PropertyTypeEnum::DateTime => $this->mapDateTimeToProperty($columnSchema, ValidationUtils::checkIntString($value)),
            PropertyTypeEnum::DateTimeImmutable => $this->mapDateTimeToProperty($columnSchema, ValidationUtils::checkIntString($value)),
            PropertyTypeEnum::Enum => $columnSchema->enumClass !== null ? $columnSchema->enumClass::from(
                ValidationUtils::checkString($value),
            ) : null,
            PropertyTypeEnum::Relation => $this->mapRelationToProperty($columnSchema, (int) $value),
            PropertyTypeEnum::Extension => $columnSchema->extensionClass !== null ?
                $this->extensionMapperProvider->getExtensionMapper($columnSchema->extensionClass)->mapToProperty(
                    $entitySchema,
                    $columnSchema,
                    $value,
                ) :
                null,
        };
    }

    public function mapToColumn(ColumnSchema $columnSchema, string|int|float|bool|object|null $value): string|int|float|null
    {
        if ($value === null) {
            if (!$columnSchema->isNullable) {
                throw new \RuntimeException(sprintf('Column "%s" is not nullable', $columnSchema->columnName));
            }

            return null;
        }

        return match ($columnSchema->propertyType) {
            PropertyTypeEnum::String => ValidationUtils::checkString($value),
            PropertyTypeEnum::Int => ValidationUtils::checkInt($value),
            PropertyTypeEnum::Float => ValidationUtils::checkFloat($value),
            PropertyTypeEnum::Bool => (int) ValidationUtils::checkBool($value),
            PropertyTypeEnum::Uuid => (string) ValidationUtils::checkUuid($value),
            PropertyTypeEnum::DateTime => $this->mapDateTimeToColumn($columnSchema, ValidationUtils::checkDatetime($value)),
            PropertyTypeEnum::DateTimeImmutable => $this->mapDateTimeToColumn($columnSchema, ValidationUtils::checkDatetime($value)),
            PropertyTypeEnum::Enum => ValidationUtils::checkEnum($value)->value,
            PropertyTypeEnum::Relation => $this->mapRelationToColumn($columnSchema, ValidationUtils::checkObject($value)),
            PropertyTypeEnum::Extension => $columnSchema->extensionClass !== null ?
                $this->extensionMapperProvider->getExtensionMapper($columnSchema->extensionClass)->mapToColumn($columnSchema, $value) :
                null,
        };
    }

    private function mapRelationToProperty(ColumnSchema $columnSchema, int $value): object
    {
        $relationEntityClass = $columnSchema->relationEntityClass ?? throw new \RuntimeException('Relation entity class not found');

        return match ($columnSchema->relationType) {
            RelationEnum::OneToMany => $this->mapRelationOneToManyToProperty(
                $relationEntityClass,
                $columnSchema->relationColumnName ?? throw new \RuntimeException('Relation column name not found'),
                $value,
            ),
            RelationEnum::ManyToOne => $this->mapRelationManyToOneToProperty($relationEntityClass, $value),
            default => throw new \RuntimeException('Relation type not found'),
        };
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Iterator<T>
     */
    private function mapRelationOneToManyToProperty(string $entityClass, string $columnName, int $value): Iterator
    {
        $reflector = new ReflectionClass(Collection::class);
        /** @var Collection<T> $lazyCollection */
        $lazyCollection = $reflector->newLazyGhost(function (Collection $object) use ($entityClass, $columnName, $value): void {
            // @phpstan-ignore-next-line constructor.call
            $object->__construct(
                iterator_to_array($this->queryProvider->select($entityClass)->where([$columnName, '=', $value])->fetchAll()),
            );
        });
        return $lazyCollection;
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return T
     */
    private function mapRelationManyToOneToProperty(string $entityClass, int $value): object
    {
        $entity = $this->entityCache->getEntity($entityClass, $value);
        if ($entity !== null) {
            return $entity;
        }

        $reflector = new ReflectionClass($entityClass);

        /** @var T $entity */
        $entity = $reflector->newLazyProxy(function (object $object) use ($entityClass, $value): object {
            $primaryColumnSchema = $this->schemaProvider->getPrimaryColumnSchema($entityClass);

            /** @var T|null $realEntity */
            $realEntity = $this->queryProvider->select($entityClass)->where([$primaryColumnSchema->columnName, '=', $value])->fetchOne();
            if ($realEntity === null) {
                throw new \RuntimeException(sprintf('Entity "%s" with id "%d" not found', $entityClass, $value));
            }

            return $realEntity;
        });

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

    private function mapDateTimeToProperty(ColumnSchema $columnSchema, string|int $value): DateTimeInterface
    {
        if ($columnSchema->propertyType === PropertyTypeEnum::DateTime) {
            if (is_int($value)) {
                return new DateTime('@' . $value);
            }

            return new DateTime($value);
        }

        if (is_int($value)) {
            return new DateTimeImmutable('@' . $value);
        }

        return new DateTimeImmutable($value);
    }

    private function mapDateTimeToColumn(ColumnSchema $columnSchema, DateTimeInterface $value): string|int
    {
        if ($columnSchema->columnType === 'timestamp') {
            return $value->getTimestamp();
        }

        if ($columnSchema->columnType === 'date') {
            return $value->format('Y-m-d');
        }

        return $value->format('Y-m-d H:i:s');
    }
}
