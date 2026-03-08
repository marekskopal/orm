<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Mapper;

use Closure;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Iterator;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Entity\EntityCache;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Utils\ValidationUtils;
use PDO;
use Ramsey\Uuid\Uuid;
use ReflectionClass;

class Mapper implements MapperInterface
{
    private readonly ExtensionMapperProvider $extensionMapperProvider;

    /** @var Closure(): QueryProvider */
    private readonly Closure $queryProviderFactory;

    /** @param Closure(): QueryProvider $queryProviderFactory */
    public function __construct(
        private readonly SchemaProvider $schemaProvider,
        private readonly EntityCache $entityCache,
        Closure $queryProviderFactory,
        private readonly DatabaseInterface $database,
    ) {
        $this->extensionMapperProvider = new ExtensionMapperProvider();
        $this->queryProviderFactory = $queryProviderFactory;
    }

    private function getQueryProvider(): QueryProvider
    {
        return ($this->queryProviderFactory)();
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
            RelationEnum::OneToOne => $this->mapRelationManyToOneToProperty($relationEntityClass, $value),
            RelationEnum::OneToOneInverse => $this->mapRelationOneToOneInverseToProperty($columnSchema, $value),
            RelationEnum::ManyToMany => $this->mapRelationManyToManyToProperty($columnSchema, $value),
            RelationEnum::ManyToManyInverse => $this->mapRelationManyToManyInverseToProperty($columnSchema, $value),
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
                iterator_to_array($this->getQueryProvider()->select($entityClass)->where([$columnName, '=', $value])->fetchAll()),
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
            $entity = $this->entityCache->getEntity($entityClass, $value);
            if ($entity !== null) {
                return $entity;
            }

            $primaryColumnSchema = $this->schemaProvider->getPrimaryColumnSchema($entityClass);

            /** @var T|null $realEntity */
            $realEntity = $this->getQueryProvider()->select($entityClass)->where(
                [$primaryColumnSchema->columnName, '=', $value],
            )->fetchOne();
            if ($realEntity === null) {
                throw new \RuntimeException(sprintf('Entity "%s" with id "%d" not found', $entityClass, $value));
            }

            return $realEntity;
        });

        return $entity;
    }

    private function mapRelationOneToOneInverseToProperty(ColumnSchema $columnSchema, int $value): object
    {
        $relationEntityClass = $columnSchema->relationEntityClass ?? throw new \RuntimeException('Relation entity class not found');
        $mappedBy = $columnSchema->mappedBy ?? throw new \RuntimeException('mappedBy not found on OneToOneInverse');

        $owningColumnSchema = $this->schemaProvider->getEntitySchema($relationEntityClass)->getColumnByPropertyName($mappedBy);
        $fkColumnName = $owningColumnSchema->columnName;

        $reflector = new ReflectionClass($relationEntityClass);

        /** @var object $proxy */
        $proxy = $reflector->newLazyProxy(function (object $object) use ($relationEntityClass, $fkColumnName, $value): object {
            $realEntity = $this->getQueryProvider()->select($relationEntityClass)->where([$fkColumnName, '=', $value])->fetchOne();
            if ($realEntity === null) {
                throw new \RuntimeException(
                    sprintf('OneToOne inverse entity "%s" not found for FK value "%d"', $relationEntityClass, $value),
                );
            }

            return $realEntity;
        });

        return $proxy;
    }

    /** @return Iterator<object> */
    private function mapRelationManyToManyToProperty(ColumnSchema $columnSchema, int $value): Iterator
    {
        $entityClass = $columnSchema->relationEntityClass ?? throw new \RuntimeException('Relation entity class not found');
        $joinTable = $columnSchema->joinTable ?? throw new \RuntimeException('joinTable not found on ManyToMany');
        $joinColumn = $columnSchema->joinColumn ?? throw new \RuntimeException('joinColumn not found on ManyToMany');
        $inverseJoinColumn = $columnSchema->inverseJoinColumn ?? throw new \RuntimeException('inverseJoinColumn not found on ManyToMany');

        $reflector = new ReflectionClass(Collection::class);
        /** @var Collection<object> $lazyCollection */
        $lazyCollection = $reflector->newLazyGhost(
            function (Collection $object) use ($entityClass, $joinTable, $joinColumn, $inverseJoinColumn, $value): void {
                $q = $this->database->getIdentifierQuoteChar();
                $stmt = $this->database->getPdo()->prepare(
                    sprintf(
                        'SELECT %1$s%2$s%1$s FROM %1$s%3$s%1$s WHERE %1$s%4$s%1$s = ?',
                        $q,
                        $inverseJoinColumn,
                        $joinTable,
                        $joinColumn,
                    ),
                );
                $stmt->execute([$value]);
                /** @var list<int> $ids */
                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if ($ids === []) {
                    // @phpstan-ignore-next-line constructor.call
                    $object->__construct([]);
                    return;
                }

                $primaryColumnSchema = $this->schemaProvider->getPrimaryColumnSchema($entityClass);
                // @phpstan-ignore-next-line constructor.call
                $object->__construct(
                    iterator_to_array(
                        $this->getQueryProvider()->select($entityClass)->where([$primaryColumnSchema->columnName, 'IN', $ids])->fetchAll(),
                    ),
                );
            },
        );

        return $lazyCollection;
    }

    /** @return Iterator<object> */
    private function mapRelationManyToManyInverseToProperty(ColumnSchema $columnSchema, int $value): Iterator
    {
        $entityClass = $columnSchema->relationEntityClass ?? throw new \RuntimeException('Relation entity class not found');
        $mappedBy = $columnSchema->mappedBy ?? throw new \RuntimeException('mappedBy not found on ManyToManyInverse');

        $reflector = new ReflectionClass(Collection::class);
        /** @var Collection<object> $lazyCollection */
        $lazyCollection = $reflector->newLazyGhost(function (Collection $object) use ($entityClass, $mappedBy, $value): void {
            $owningColumnSchema = $this->schemaProvider->getEntitySchema($entityClass)->getColumnByPropertyName($mappedBy);
            $joinTable = $owningColumnSchema->joinTable ?? throw new \RuntimeException('joinTable not found on owning ManyToMany');
            $joinColumn = $owningColumnSchema->joinColumn ?? throw new \RuntimeException('joinColumn not found on owning ManyToMany');
            $inverseJoinColumn = $owningColumnSchema->inverseJoinColumn ?? throw new \RuntimeException(
                'inverseJoinColumn not found on owning ManyToMany',
            );

            $q = $this->database->getIdentifierQuoteChar();
            $stmt = $this->database->getPdo()->prepare(
                sprintf('SELECT %1$s%2$s%1$s FROM %1$s%3$s%1$s WHERE %1$s%4$s%1$s = ?', $q, $joinColumn, $joinTable, $inverseJoinColumn),
            );
            $stmt->execute([$value]);
            /** @var list<int> $ids */
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if ($ids === []) {
                // @phpstan-ignore-next-line constructor.call
                $object->__construct([]);
                return;
            }

            $primaryColumnSchema = $this->schemaProvider->getPrimaryColumnSchema($entityClass);
            // @phpstan-ignore-next-line constructor.call
            $object->__construct(
                iterator_to_array(
                    $this->getQueryProvider()->select($entityClass)->where([$primaryColumnSchema->columnName, 'IN', $ids])->fetchAll(),
                ),
            );
        });

        return $lazyCollection;
    }

    private function mapRelationToColumn(ColumnSchema $columnSchema, object $value): int
    {
        $relationEntityClass = $columnSchema->relationEntityClass ?? throw new \RuntimeException('Relation entity class not found');

        return match ($columnSchema->relationType) {
            RelationEnum::ManyToOne, RelationEnum::OneToOne => $this->mapRelationManyToOneToColumn($relationEntityClass, $value),
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

    private function mapDateTimeToColumn(ColumnSchema $columnSchema, DateTimeInterface $value): string
    {
        return match ($columnSchema->columnType) {
            Type::Date => $value->format('Y-m-d'),
            Type::Time => $value->format('H:i:s'),
            default => $value->format('Y-m-d H:i:s'),
        };
    }
}
