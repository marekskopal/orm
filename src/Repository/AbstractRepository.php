<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Repository;

use Iterator;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Query\Where\WhereBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Enum\CascadeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

/**
 * @template T of object
 * @implements RepositoryInterface<T>
 * @phpstan-import-type Where from WhereBuilder
 */
abstract class AbstractRepository implements RepositoryInterface
{
    /** @param class-string<T> $entityClass */
    public function __construct(
        protected readonly string $entityClass,
        protected readonly QueryProvider $queryProvider,
        protected readonly SchemaProvider $schemaProvider,
    ) {
    }

    /** @return Select<T> */
    public function select(): Select
    {
        return $this->queryProvider->select($this->entityClass);
    }

    /**
     * @param Where $where
     * @return Iterator<T>
     */
    public function findAll(array|callable $where = []): Iterator
    {
        return $this->select()->where($where)->fetchAll();
    }

    /**
     * @param Where $where
     * @return T|null
     */
    public function findOne(array|callable $where = []): ?object
    {
        return $this->select()->where($where)->fetchOne();
    }

    /** @param T $entity */
    public function persist(object $entity): void
    {
        $entitySchema = $this->schemaProvider->getEntitySchema($entity::class);

        // Cascade persist owning-side relations before the entity (so their PKs are available for FK columns)
        foreach ($entitySchema->columns as $columnSchema) {
            if (!in_array(CascadeEnum::Persist, $columnSchema->cascade, true)) {
                continue;
            }

            if (
                $columnSchema->relationType !== RelationEnum::ManyToOne
                && $columnSchema->relationType !== RelationEnum::OneToOne
            ) {
                continue;
            }

            // @phpstan-ignore-next-line property.dynamicName
            $related = $entity->{$columnSchema->propertyName};
            if ($related === null) {
                continue;
            }

            // @phpstan-ignore-next-line argument.type
            $this->persistEntity($related);
        }

        // Persist the entity itself
        $primaryColumnSchema = $entitySchema->getPrimaryColumn();
        // @phpstan-ignore-next-line property.dynamicName
        if (!isset($entity->{$primaryColumnSchema->columnName})) {
            $this->queryProvider->insert($entity::class)->entity($entity)->execute();
        } else {
            $this->queryProvider->update($entity::class)->entity($entity)->execute();
        }

        // Cascade persist collection-side relations after the entity (entity PK is now available)
        foreach ($entitySchema->columns as $columnSchema) {
            if (!in_array(CascadeEnum::Persist, $columnSchema->cascade, true)) {
                continue;
            }

            if ($columnSchema->relationType === RelationEnum::OneToMany) {
                // @phpstan-ignore-next-line property.dynamicName
                foreach ($entity->{$columnSchema->propertyName} as $related) {
                    // @phpstan-ignore-next-line argument.type
                    $this->persistEntity($related);
                }
            }

            if ($columnSchema->relationType === RelationEnum::OneToOneInverse) {
                // @phpstan-ignore-next-line property.dynamicName
                $related = $entity->{$columnSchema->propertyName};
                if ($related !== null) {
                    // @phpstan-ignore-next-line argument.type
                    $this->persistEntity($related);
                }
            }

            if ($columnSchema->relationType !== RelationEnum::ManyToMany) {
                continue;
            }

            // @phpstan-ignore-next-line property.dynamicName
            foreach ($entity->{$columnSchema->propertyName} as $related) {
                // @phpstan-ignore-next-line argument.type
                $this->persistEntity($related);
            }

            $this->syncManyToManyJoinTable($entity, $columnSchema);
        }
    }

    /** @param T $entity */
    public function delete(object $entity): void
    {
        $entitySchema = $this->schemaProvider->getEntitySchema($entity::class);

        // Cascade remove collection-side relations before the entity (avoid FK constraint violations)
        foreach ($entitySchema->columns as $columnSchema) {
            if (!in_array(CascadeEnum::Remove, $columnSchema->cascade, true)) {
                continue;
            }

            if ($columnSchema->relationType === RelationEnum::OneToMany) {
                // @phpstan-ignore-next-line property.dynamicName
                foreach ($entity->{$columnSchema->propertyName} as $related) {
                    // @phpstan-ignore-next-line argument.type
                    $this->deleteEntity($related);
                }
            }

            if ($columnSchema->relationType === RelationEnum::OneToOneInverse) {
                // @phpstan-ignore-next-line property.dynamicName
                $related = $entity->{$columnSchema->propertyName};
                if ($related !== null) {
                    // @phpstan-ignore-next-line argument.type
                    $this->deleteEntity($related);
                }
            }

            if ($columnSchema->relationType === RelationEnum::ManyToMany) {
                $this->deleteManyToManyJoinRows($entity, $columnSchema);
            }
        }

        $this->queryProvider->delete($entity::class)->entity($entity)->execute();
    }

    private function persistEntity(object $entity): void
    {
        $primaryColumnSchema = $this->schemaProvider->getPrimaryColumnSchema($entity::class);
        // @phpstan-ignore-next-line property.dynamicName
        if (!isset($entity->{$primaryColumnSchema->columnName})) {
            $this->queryProvider->insert($entity::class)->entity($entity)->execute();
        } else {
            $this->queryProvider->update($entity::class)->entity($entity)->execute();
        }
    }

    private function deleteEntity(object $entity): void
    {
        $this->queryProvider->delete($entity::class)->entity($entity)->execute();
    }

    private function syncManyToManyJoinTable(object $entity, ColumnSchema $columnSchema): void
    {
        $joinTable = $columnSchema->joinTable ?? throw new \RuntimeException('joinTable not set on ManyToMany column');
        $joinColumn = $columnSchema->joinColumn ?? throw new \RuntimeException('joinColumn not set on ManyToMany column');
        $inverseJoinColumn = $columnSchema->inverseJoinColumn ?? throw new \RuntimeException(
            'inverseJoinColumn not set on ManyToMany column',
        );
        $relatedEntityClass = $columnSchema->relationEntityClass ?? throw new \RuntimeException(
            'relationEntityClass not set on ManyToMany column',
        );

        $entityPk = $this->schemaProvider->getPrimaryColumnSchema($entity::class);
        $relatedPk = $this->schemaProvider->getPrimaryColumnSchema($relatedEntityClass);

        // @phpstan-ignore-next-line property.dynamicName
        $entityId = $entity->{$entityPk->propertyName};

        $database = $this->queryProvider->getDatabase();
        $pdo = $database->getPdo();
        $q = $database->getIdentifierQuoteChar();

        $pdo->prepare(sprintf('DELETE FROM %1$s%2$s%1$s WHERE %1$s%3$s%1$s = ?', $q, $joinTable, $joinColumn))->execute([$entityId]);

        // @phpstan-ignore-next-line property.dynamicName
        foreach ($entity->{$columnSchema->propertyName} as $related) {
            // @phpstan-ignore-next-line property.dynamicName
            $relatedId = $related->{$relatedPk->propertyName};
            $pdo->prepare(
                sprintf(
                    'INSERT INTO %1$s%2$s%1$s (%1$s%3$s%1$s, %1$s%4$s%1$s) VALUES (?, ?)',
                    $q,
                    $joinTable,
                    $joinColumn,
                    $inverseJoinColumn,
                ),
            )->execute([$entityId, $relatedId]);
        }
    }

    private function deleteManyToManyJoinRows(object $entity, ColumnSchema $columnSchema): void
    {
        $joinTable = $columnSchema->joinTable ?? throw new \RuntimeException('joinTable not set on ManyToMany column');
        $joinColumn = $columnSchema->joinColumn ?? throw new \RuntimeException('joinColumn not set on ManyToMany column');

        $entityPk = $this->schemaProvider->getPrimaryColumnSchema($entity::class);
        // @phpstan-ignore-next-line property.dynamicName
        $entityId = $entity->{$entityPk->propertyName};

        $database = $this->queryProvider->getDatabase();
        $q = $database->getIdentifierQuoteChar();

        $database->getPdo()->prepare(sprintf('DELETE FROM %1$s%2$s%1$s WHERE %1$s%3$s%1$s = ?', $q, $joinTable, $joinColumn))->execute(
            [$entityId],
        );
    }
}
