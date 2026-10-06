<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\UnitOfWork;

use Closure;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Entity\IdentityMap;
use MarekSkopal\ORM\Mapper\Collection;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Relation\RelationResolver;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Enum\CascadeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Utils\QuoteUtils;
use PDO;
use ReflectionClass;

/**
 * Collects entities to write and writes them in one flush():
 *
 * 1. inserts, parents before children, grouped into one multi-row INSERT per class and level;
 * 2. updates of the columns that changed since the entity was read or last written;
 * 3. join-table changes of initialised ManyToMany collections;
 * 4. deletes, children before parents, grouped into one DELETE ... IN per class and level.
 *
 * Only entities passed to persist() or remove(), and the entities their cascade relations reach,
 * are written; flush() does not scan every entity that was ever loaded.
 */
class UnitOfWork
{
    /** Bound parameters per statement; below SQLite's 32766, MySQL's and PostgreSQL's 65535. */
    private const int MaxParameters = 30000;

    /** @var array<int, object> object id => entity, in scheduling order */
    private array $persisted = [];

    /** @var array<int, object> object id => entity, in scheduling order */
    private array $removed = [];

    /** @var array<class-string, Closure(object, string): mixed> */
    private array $propertyReaders = [];

    /** @var array<class-string, ReflectionClass<object>> */
    private array $reflectionClasses = [];

    /** @var array<string, list<ColumnSchema>> cache of the relation-column lists, see relationColumns() */
    private array $columnCache = [];

    /** @var list<Closure(): void> undoes the in-memory effects of the running flush if it fails */
    private array $undo = [];

    public function __construct(
        private readonly DatabaseInterface $database,
        private readonly SchemaProvider $schemaProvider,
        private readonly IdentityMap $identityMap,
        private readonly QueryProvider $queryProvider,
        private readonly RelationResolver $relationResolver,
    ) {
    }

    /** Schedules an entity to be inserted or updated, together with its cascade-persist relations. */
    public function persist(object $entity): self
    {
        unset($this->removed[spl_object_id($entity)]);
        $this->persisted[spl_object_id($entity)] = $entity;

        return $this;
    }

    /** Schedules an entity to be deleted, together with its cascade-remove relations. */
    public function remove(object $entity): self
    {
        unset($this->persisted[spl_object_id($entity)]);
        $this->removed[spl_object_id($entity)] = $entity;

        return $this;
    }

    /** Whether an entity was read from or written to the database through this ORM. */
    public function isManaged(object $entity): bool
    {
        if ($this->identityMap->hasSnapshot($entity)) {
            return true;
        }

        // An uninitialised proxy has no snapshot yet but is the registered instance for its id.
        return $this->isUninitialised($entity)
            && $this->identityMap->contains($entity, $this->schemaProvider->getPrimaryKey($entity));
    }

    /**
     * Writes the scheduled work in one transaction (or inside the caller's open transaction); work
     * that is a single statement at most needs none.
     *
     * If a statement fails, the transaction is rolled back, the in-memory effects of the flush are
     * undone (ids assigned to inserted entities, identity-map registrations, snapshots) and the work
     * stays scheduled, so flush() can be retried. Inside a transaction the caller opened, nothing is
     * rolled back or undone: the caller decides what happens to the statements already run.
     */
    public function flush(): void
    {
        if ($this->persisted === [] && $this->removed === []) {
            return;
        }

        $toDelete = $this->collectCascade($this->removed, CascadeEnum::Remove);
        $toWrite = array_diff_key($this->collectCascade($this->persisted, CascadeEnum::Persist), $toDelete);

        // Work that is a single statement at most is atomic without a transaction.
        $pdo = $this->database->getPdo();
        $callerTransaction = $pdo->inTransaction();
        $ownTransaction = !$callerTransaction && $this->mayWriteSeveralStatements($toWrite, $toDelete);
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        $this->undo = [];

        try {
            $this->write($toWrite, $toDelete);
            if ($ownTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                $this->rollBack($pdo);
            }

            if (!$callerTransaction) {
                foreach (array_reverse($this->undo) as $undo) {
                    $undo();
                }
            }

            $this->undo = [];

            throw $e;
        }

        $this->undo = [];
        $this->clear();
    }

    private function rollBack(PDO $pdo): void
    {
        // A failed commit may already have ended the transaction.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    /**
     * @param array<int, object> $toWrite
     * @param array<int, object> $toDelete
     */
    private function mayWriteSeveralStatements(array $toWrite, array $toDelete): bool
    {
        $entities = $toWrite + $toDelete;
        if (count($entities) !== 1) {
            return count($entities) > 1;
        }

        // One entity is one statement, unless it also has join rows to write or delete.
        return $this->joinTableColumns(reset($entities)::class) !== [];
    }

    /**
     * @param array<int, object> $toWrite
     * @param array<int, object> $toDelete
     */
    private function write(array $toWrite, array $toDelete): void
    {
        $inserts = [];
        $updates = [];
        $unknownKeys = [];
        foreach ($toWrite as $id => $entity) {
            if ($this->isUninitialised($entity)) {
                unset($toWrite[$id]);
            } elseif ($this->isManaged($entity)) {
                $updates[$id] = $entity;
            } elseif (!$this->schemaProvider->getPrimaryColumnSchema($entity::class)->isAutoIncrement) {
                // A key the caller chose: the row may exist already, e.g. after the identity map was cleared.
                $unknownKeys[$id] = $entity;
            } elseif ($this->schemaProvider->hasPrimaryKey($entity)) {
                // A detached entity with an auto-increment id already set is an existing row.
                $updates[$id] = $entity;
            } else {
                $inserts[$id] = $entity;
            }
        }

        $existing = $this->findExisting($unknownKeys);
        $updates += array_intersect_key($unknownKeys, $existing);
        $inserts += array_diff_key($unknownKeys, $existing);

        $this->insert($inserts);
        foreach ($updates as $entity) {
            $this->update($entity);
        }

        foreach ($toWrite as $id => $entity) {
            $this->syncJoinTables($entity, isset($inserts[$id]));
        }

        $this->delete($toDelete);
    }

    /**
     * Reloads an entity's mapped properties from the database, discarding unflushed changes.
     * Readonly properties keep their value; collections become lazy again.
     */
    public function refresh(object $entity): void
    {
        $entitySchema = $this->schemaProvider->getEntitySchema($entity::class);
        $primaryColumnName = $entitySchema->getPrimaryColumn()->columnName;

        $select = $this->queryProvider->select($entity::class)->where(
            [$primaryColumnName, '=', $this->schemaProvider->getPrimaryKeyValue($entity)],
        );
        $row = $select->fetchAssocOne() ?? throw new \RuntimeException(
            sprintf('Entity "%s" with id "%s" no longer exists.', $entity::class, $this->schemaProvider->getPrimaryKeyValue($entity)),
        );

        // Hydrate a detached copy (the identity map is not consulted) and copy its state over. Only a
        // registered entity keeps a snapshot; the identity map keeps those alive.
        $fresh = ($this->schemaProvider->getHydrator($entity::class))($row, $this->relationResolver);
        if ($this->identityMap->contains($entity, $this->schemaProvider->getPrimaryKey($entity))) {
            $this->identityMap->moveSnapshot($fresh, $entity);
        } else {
            $this->identityMap->removeSnapshot($fresh);
        }

        $reflectionClass = new ReflectionClass($entity);
        foreach (array_keys($entitySchema->columns) as $propertyName) {
            $property = $reflectionClass->getProperty($propertyName);
            if ($property->isReadOnly()) {
                continue;
            }

            $property->setValue($entity, $property->getValue($fresh));
        }
    }

    /** Forgets all scheduled work without writing it. */
    public function clear(): void
    {
        $this->persisted = [];
        $this->removed = [];
    }

    /**
     * The roots plus every entity their cascade relations reach, breadth first so collection order
     * is kept. Removal follows only child relations (collections and inverse sides) and loads lazy
     * collections, since their items must be deleted; persisting skips uninitialised relations,
     * which cannot hold changes.
     *
     * @param array<int, object> $roots
     * @return array<int, object>
     */
    private function collectCascade(array $roots, CascadeEnum $cascade): array
    {
        $collected = [];
        $queue = array_values($roots);
        for ($i = 0; $i < count($queue); $i++) {
            $entity = $queue[$i];
            $id = spl_object_id($entity);
            if (isset($collected[$id])) {
                continue;
            }

            $collected[$id] = $entity;
            if ($cascade === CascadeEnum::Persist && $this->isUninitialised($entity)) {
                continue;
            }

            array_push($queue, ...$this->related($entity, $cascade, onlyChildren: $cascade === CascadeEnum::Remove));
        }

        return $collected;
    }

    /**
     * The entities reachable through the cascade relations of one entity.
     *
     * @return list<object>
     */
    private function related(object $entity, CascadeEnum $cascade, bool $onlyChildren = false): array
    {
        $related = [];
        $columns = $onlyChildren ? $this->childCascadeColumns($entity::class, $cascade) : $this->cascadeColumns($entity::class, $cascade);
        foreach ($columns as $columnSchema) {
            $value = $this->readProperty($entity, $columnSchema->propertyName);
            if ($value instanceof Collection) {
                if (!$onlyChildren && !$value->isInitialized()) {
                    continue;
                }

                array_push($related, ...array_values($value->toArray()));
            } elseif (is_object($value)) {
                $related[] = $value;
            }
        }

        return $related;
    }

    /**
     * Relations with the given cascade.
     *
     * @param class-string $entityClass
     * @return list<ColumnSchema>
     */
    private function cascadeColumns(string $entityClass, CascadeEnum $cascade): array
    {
        return $this->relationColumns(
            $entityClass,
            'cascade:' . $cascade->name,
            static fn(ColumnSchema $column): bool => in_array($cascade, $column->cascade, true),
        );
    }

    /**
     * Relations with the given cascade that lead to children: no owning relations, and no
     * ManyToMany, whose removal only removes join rows.
     *
     * @param class-string $entityClass
     * @return list<ColumnSchema>
     */
    private function childCascadeColumns(string $entityClass, CascadeEnum $cascade): array
    {
        return $this->relationColumns(
            $entityClass,
            'children:' . $cascade->name,
            static fn(ColumnSchema $column): bool => in_array($cascade, $column->cascade, true) && in_array(
                $column->relationType,
                [RelationEnum::OneToMany, RelationEnum::OneToOneInverse],
                true,
            ),
        );
    }

    /**
     * Owning ManyToMany relations.
     *
     * @param class-string $entityClass
     * @return list<ColumnSchema>
     */
    private function owningManyToManyColumns(string $entityClass): array
    {
        return $this->relationColumns(
            $entityClass,
            'manyToMany',
            static fn(ColumnSchema $column): bool => $column->relationType === RelationEnum::ManyToMany,
        );
    }

    /**
     * ManyToMany relations of either side.
     *
     * @param class-string $entityClass
     * @return list<ColumnSchema>
     */
    private function joinTableColumns(string $entityClass): array
    {
        return $this->relationColumns(
            $entityClass,
            'joinTables',
            static fn(ColumnSchema $column): bool => in_array(
                $column->relationType,
                [RelationEnum::ManyToMany, RelationEnum::ManyToManyInverse],
                true,
            ),
        );
    }

    /**
     * The columns of a class matching a filter, computed once per class and cache key.
     *
     * @param class-string $entityClass
     * @param Closure(ColumnSchema): bool $filter
     * @return list<ColumnSchema>
     */
    private function relationColumns(string $entityClass, string $cacheKey, Closure $filter): array
    {
        $key = $entityClass . '|' . $cacheKey;

        return $this->columnCache[$key] ??= array_values(array_filter(
            $this->schemaProvider->getEntitySchema($entityClass)->columns,
            $filter,
        ));
    }

    /**
     * The entities, among unmanaged ones with a key the caller chose, whose row exists already: one
     * query per class.
     *
     * @param array<int, object> $entities
     * @return array<int, object>
     */
    private function findExisting(array $entities): array
    {
        /** @var array<class-string, array<int|string, list<int>>> $byClassAndKey */
        $byClassAndKey = [];
        foreach ($entities as $id => $entity) {
            if ($this->schemaProvider->hasPrimaryKey($entity)) {
                $byClassAndKey[$entity::class][$this->schemaProvider->getPrimaryKeyValue($entity)][] = $id;
            }
        }

        $existing = [];
        foreach ($byClassAndKey as $entityClass => $idsByKey) {
            $primaryColumnName = $this->schemaProvider->getPrimaryColumnSchema($entityClass)->columnName;
            foreach (array_chunk(array_keys($idsByKey), self::MaxParameters) as $keys) {
                $rows = $this->queryProvider->select($entityClass)
                    ->columns([$primaryColumnName])
                    ->where([$primaryColumnName, 'IN', $keys])
                    ->fetchAssocAll();
                foreach ($rows as $row) {
                    $key = $row[$primaryColumnName] ?? null;
                    if (!is_int($key) && !is_string($key)) {
                        continue;
                    }

                    foreach ($idsByKey[$key] ?? [] as $id) {
                        $existing[$id] = $entities[$id];
                    }
                }
            }
        }

        return $existing;
    }

    /** @param array<int, object> $entities */
    private function insert(array $entities): void
    {
        if ($entities === []) {
            return;
        }

        foreach ($this->insertLevels($entities) as $level) {
            /** @var array<class-string, list<object>> $byClass */
            $byClass = [];
            foreach ($level as $entity) {
                $byClass[$entity::class][] = $entity;
            }

            foreach ($byClass as $entityClass => $classEntities) {
                $columnCount = max(1, count($this->schemaProvider->getEntitySchema($entityClass)->getInsertableColumns()));
                foreach (array_chunk($classEntities, max(1, intdiv(self::MaxParameters, $columnCount))) as $chunk) {
                    $insert = $this->queryProvider->insert($entityClass);
                    foreach ($chunk as $entity) {
                        $insert->entity($entity);
                    }

                    $insert->execute();
                    $primaryColumn = $this->schemaProvider->getPrimaryColumnSchema($entityClass);
                    foreach ($insert->getExtractedValues() as $index => $values) {
                        $entity = $chunk[$index];
                        $primaryKey = $this->schemaProvider->getPrimaryKey($entity);
                        unset($values[$primaryColumn->columnName]);
                        $this->identityMap->add($entity, $primaryKey);
                        $this->identityMap->setSnapshot($entity, $values);

                        $this->undo[] = function () use ($entity, $primaryKey, $primaryColumn): void {
                            $this->identityMap->remove($entity, $primaryKey);
                            if ($primaryColumn->isAutoIncrement) {
                                $this->schemaProvider->clearPrimaryKey($entity);
                            }
                        };
                    }
                }
            }
        }
    }

    /**
     * Orders new entities so every entity comes after the new entities its owning relations
     * reference: level 0 references none, level n references at least one entity of level n - 1.
     *
     * @param array<int, object> $entities
     * @return list<list<object>>
     */
    private function insertLevels(array $entities): array
    {
        if (count($entities) === 1) {
            return [array_values($entities)];
        }

        /** @var array<int, int> $levels */
        $levels = [];
        $resolve = function (object $entity, array $path) use (&$resolve, &$levels, $entities): int {
            $id = spl_object_id($entity);
            if (isset($levels[$id])) {
                return $levels[$id];
            }

            if (isset($path[$id])) {
                throw new \LogicException(sprintf('Cannot write "%s": its new relations form a cycle.', $entity::class));
            }

            $path[$id] = true;
            $level = 0;
            foreach ($this->owningRelations($entity) as $related) {
                if (isset($entities[spl_object_id($related)])) {
                    $level = max($level, $resolve($related, $path) + 1);
                }
            }

            $levels[$id] = $level;

            return $level;
        };

        $byLevel = [];
        foreach ($entities as $entity) {
            $byLevel[$resolve($entity, [])][] = $entity;
        }

        ksort($byLevel);

        return array_values($byLevel);
    }

    /** @return list<object> */
    private function owningRelations(object $entity): array
    {
        // An uninitialised proxy is never new, and for deletes reading its relations would load it
        // (or fail if its row is gone): it is ordered first among the entities it belongs with.
        if ($this->isUninitialised($entity)) {
            return [];
        }

        $related = [];
        foreach ($this->schemaProvider->getEntitySchema($entity::class)->getInsertableColumns() as $columnSchema) {
            if ($columnSchema->relationType === RelationEnum::ManyToOne || $columnSchema->relationType === RelationEnum::OneToOne) {
                $value = $this->readProperty($entity, $columnSchema->propertyName);
                if (is_object($value)) {
                    $related[] = $value;
                }
            }
        }

        return $related;
    }

    private function update(object $entity): void
    {
        $current = $this->updatableValues($entity);
        $snapshot = $this->identityMap->getSnapshot($entity);

        if ($snapshot === null) {
            // A detached entity: nothing to compare with, so every column is written.
            $changed = $current;
        } else {
            $original = array_is_list($snapshot) && $snapshot !== []
                ? $this->schemaProvider->normalizeSnapshot($entity, $snapshot)
                : $snapshot;
            $changed = [];
            foreach ($current as $column => $value) {
                if (!array_key_exists($column, $original) || $original[$column] !== $value) {
                    $changed[$column] = $value;
                }
            }
        }

        if ($changed !== []) {
            $this->queryProvider->update($entity::class)->entity($entity)->values($changed)->execute();
        }

        // A detached entity becomes managed, unless another instance is already registered for its id;
        // snapshots are only kept for registered entities.
        $primaryKey = $this->schemaProvider->getPrimaryKey($entity);
        $registered = false;
        if ($this->identityMap->get($entity::class, $primaryKey) === null) {
            $this->identityMap->add($entity, $primaryKey);
            $registered = true;
        }

        if ($this->identityMap->contains($entity, $primaryKey)) {
            $this->identityMap->setSnapshot($entity, $current);
        }

        $this->undo[] = function () use ($entity, $primaryKey, $snapshot, $registered): void {
            if ($registered) {
                $this->identityMap->remove($entity, $primaryKey);
            } elseif ($snapshot !== null) {
                $this->identityMap->setSnapshot($entity, $snapshot);
            }
        };
    }

    /**
     * The extracted values of the updatable columns, keyed by column name.
     *
     * @return array<string, string|int|float|null>
     */
    private function updatableValues(object $entity): array
    {
        $values = $this->schemaProvider->extract($entity);
        $primaryColumnName = $this->schemaProvider->getPrimaryColumnSchema($entity::class)->columnName;
        unset($values[$primaryColumnName]);

        return $values;
    }

    /** Brings the join rows of initialised owning ManyToMany collections in line with the collections. */
    private function syncJoinTables(object $entity, bool $isNew): void
    {
        foreach ($this->owningManyToManyColumns($entity::class) as $columnSchema) {
            $collection = $this->readProperty($entity, $columnSchema->propertyName);
            if (!$collection instanceof Collection || !$collection->isInitialized()) {
                continue;
            }

            $this->syncJoinTable($entity, $columnSchema, $collection, $isNew);
        }
    }

    /** @param Collection<object> $collection */
    private function syncJoinTable(object $entity, ColumnSchema $columnSchema, Collection $collection, bool $isNew): void
    {
        $joinTable = $columnSchema->joinTable ?? throw new \LogicException('ManyToMany relation has no join table.');
        $joinColumn = $columnSchema->joinColumn ?? throw new \LogicException('ManyToMany relation has no join column.');
        $inverseJoinColumn = $columnSchema->inverseJoinColumn ?? throw new \LogicException(
            'ManyToMany relation has no inverse join column.',
        );

        $ownerId = $this->schemaProvider->getPrimaryKeyValue($entity);
        $quoteChar = $this->database->getIdentifierQuoteChar();
        $table = QuoteUtils::quote($joinTable, $quoteChar);
        $joinColumnSql = QuoteUtils::quote($joinColumn, $quoteChar);
        $inverseColumnSql = QuoteUtils::quote($inverseJoinColumn, $quoteChar);

        $wanted = [];
        foreach ($collection as $related) {
            $relatedId = $this->schemaProvider->getPrimaryKeyValue($related);
            $wanted[$relatedId] = $relatedId;
        }

        $existing = [];
        if (!$isNew) {
            $statement = $this->database->execute(
                'SELECT ' . $inverseColumnSql . ' FROM ' . $table . ' WHERE ' . $joinColumnSql . ' = ?',
                [$ownerId],
            );
            /** @var list<int|string> $existingIds */
            $existingIds = $statement->fetchAll(PDO::FETCH_COLUMN);
            foreach ($existingIds as $existingId) {
                $existing[$existingId] = $existingId;
            }
        }

        $toDelete = array_values(array_diff_key($existing, $wanted));
        if ($toDelete !== []) {
            $this->database->execute(
                'DELETE FROM ' . $table . ' WHERE ' . $joinColumnSql . ' = ? AND ' . $inverseColumnSql
                . ' IN (' . implode(',', array_fill(0, count($toDelete), '?')) . ')',
                [$ownerId, ...$toDelete],
            );
        }

        $toInsert = array_values(array_diff_key($wanted, $existing));
        foreach (array_chunk($toInsert, max(1, intdiv(self::MaxParameters, 2))) as $chunk) {
            $parameters = [];
            foreach ($chunk as $relatedId) {
                array_push($parameters, $ownerId, $relatedId);
            }

            $this->database->execute(
                'INSERT INTO ' . $table . ' (' . $joinColumnSql . ', ' . $inverseColumnSql . ') VALUES '
                . implode(',', array_fill(0, count($chunk), '(?, ?)')),
                $parameters,
            );
        }
    }

    /** @param array<int, object> $entities */
    private function delete(array $entities): void
    {
        if ($entities === []) {
            return;
        }

        // Join rows of owning ManyToMany relations go first; they reference the deleted rows.
        foreach ($entities as $entity) {
            $this->deleteJoinRows($entity);
        }

        // Children reference parents, so the deepest insert level is deleted first.
        foreach (array_reverse($this->insertLevels($entities)) as $level) {
            /** @var array<class-string, list<object>> $byClass */
            $byClass = [];
            foreach ($level as $entity) {
                $byClass[$entity::class][] = $entity;
            }

            foreach ($byClass as $entityClass => $classEntities) {
                foreach (array_chunk($classEntities, self::MaxParameters) as $chunk) {
                    $delete = $this->queryProvider->delete($entityClass);
                    foreach ($chunk as $entity) {
                        $delete->entity($entity);
                    }

                    $delete->execute();
                    foreach ($chunk as $entity) {
                        $primaryKey = $this->schemaProvider->getPrimaryKey($entity);
                        $registered = $this->identityMap->contains($entity, $primaryKey);
                        $snapshot = $this->identityMap->getSnapshot($entity);
                        $this->identityMap->remove($entity, $primaryKey);

                        $this->undo[] = function () use ($entity, $primaryKey, $registered, $snapshot): void {
                            if ($registered) {
                                $this->identityMap->add($entity, $primaryKey);
                            }

                            if ($snapshot !== null) {
                                $this->identityMap->setSnapshot($entity, $snapshot);
                            }
                        };
                    }
                }
            }
        }
    }

    /** Deletes the join rows that reference an entity, from either side of a ManyToMany relation. */
    private function deleteJoinRows(object $entity): void
    {
        $quoteChar = $this->database->getIdentifierQuoteChar();
        foreach ($this->joinTableColumns($entity::class) as $columnSchema) {
            if ($columnSchema->relationType === RelationEnum::ManyToMany) {
                $joinTable = $columnSchema->joinTable;
                $column = $columnSchema->joinColumn;
            } else {
                // The inverse side: the owning relation's join table, matched on its inverse column.
                $owningColumnSchema = $this->schemaProvider->getEntitySchema(
                    $columnSchema->relationEntityClass ?? throw new \LogicException('ManyToMany relation has no entity class.'),
                )->getColumnByPropertyName(
                    $columnSchema->mappedBy ?? throw new \LogicException('Inverse ManyToMany relation has no mappedBy.'),
                );
                $joinTable = $owningColumnSchema->joinTable;
                $column = $owningColumnSchema->inverseJoinColumn;
            }

            if ($joinTable === null || $column === null) {
                throw new \LogicException(sprintf('ManyToMany relation "%s" has no join table or column.', $columnSchema->propertyName));
            }

            $this->database->execute(
                'DELETE FROM ' . QuoteUtils::quote($joinTable, $quoteChar) . ' WHERE ' . QuoteUtils::quote($column, $quoteChar) . ' = ?',
                [$this->schemaProvider->getPrimaryKeyValue($entity)],
            );
        }
    }

    /** Reads a property in the entity's scope, so private properties work; unset properties read as null. */
    private function readProperty(object $entity, string $propertyName): mixed
    {
        $reader = $this->propertyReaders[$entity::class] ??= Closure::bind(
            // @phpstan-ignore-next-line property.dynamicName
            static fn(object $entity, string $propertyName): mixed => $entity->{$propertyName} ?? null,
            null,
            $entity::class,
        );

        return $reader($entity, $propertyName);
    }

    private function isUninitialised(object $entity): bool
    {
        if ($entity instanceof Collection) {
            return !$entity->isInitialized();
        }

        $entityClass = $entity::class;
        if (!isset($this->reflectionClasses[$entityClass])) {
            $this->reflectionClasses[$entityClass] = new ReflectionClass($entityClass);
        }

        return $this->reflectionClasses[$entityClass]->isUninitializedLazyObject($entity);
    }
}
