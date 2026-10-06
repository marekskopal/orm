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

    /** @var array<string, list<ColumnSchema>> "class|kind" => columns, see columnsOf() */
    private array $columnCache = [];

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
     * Writes the scheduled work in one transaction (or inside the caller's open transaction). If a
     * statement fails, the transaction is rolled back and the work stays scheduled; entities written
     * before the failure keep the ids they were given, so clear the identity map before retrying.
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
        $ownTransaction = $this->mayWriteSeveralStatements($toWrite, $toDelete) && !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $this->write($toWrite, $toDelete);
            if ($ownTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                $this->rollBack($pdo);
            }

            throw $e;
        }

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
        return $this->columnsOf(reset($entities)::class, 'joinTables') !== [];
    }

    /**
     * @param array<int, object> $toWrite
     * @param array<int, object> $toDelete
     */
    private function write(array $toWrite, array $toDelete): void
    {
        $inserts = [];
        $updates = [];
        foreach ($toWrite as $id => $entity) {
            if ($this->isUninitialised($entity)) {
                unset($toWrite[$id]);
            } elseif ($this->isNew($entity)) {
                $inserts[$id] = $entity;
            } else {
                $updates[$id] = $entity;
            }
        }

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

        // Hydrate a detached copy (the identity map is not consulted) and copy its state over.
        $fresh = ($this->schemaProvider->getHydrator($entity::class))($row, $this->relationResolver);
        $this->identityMap->moveSnapshot($fresh, $entity);

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
        foreach ($this->columnsOf(
            $entity::class,
            $onlyChildren ? 'children:' . $cascade->name : 'cascade:' . $cascade->name,
        ) as $columnSchema) {
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
     * Relation columns of a class, computed once per class:
     * - "cascade:<Cascade>": relations with that cascade;
     * - "children:<Cascade>": the same without owning relations and without ManyToMany, whose removal
     *   only removes join rows;
     * - "manyToMany": owning ManyToMany relations;
     * - "joinTables": ManyToMany relations of either side.
     *
     * @param class-string $entityClass
     * @return list<ColumnSchema>
     */
    private function columnsOf(string $entityClass, string $kind): array
    {
        $cacheKey = $entityClass . '|' . $kind;
        if (isset($this->columnCache[$cacheKey])) {
            return $this->columnCache[$cacheKey];
        }

        $columns = [];
        foreach ($this->schemaProvider->getEntitySchema($entityClass)->columns as $columnSchema) {
            $relationType = $columnSchema->relationType;
            $isOwning = $relationType === RelationEnum::ManyToOne || $relationType === RelationEnum::OneToOne;
            $isManyToMany = $relationType === RelationEnum::ManyToMany || $relationType === RelationEnum::ManyToManyInverse;

            $matches = match (true) {
                $kind === 'manyToMany' => $relationType === RelationEnum::ManyToMany,
                $kind === 'joinTables' => $isManyToMany,
                str_starts_with($kind, 'cascade:') => in_array(
                    constant(CascadeEnum::class . '::' . substr($kind, 8)),
                    $columnSchema->cascade,
                    true,
                ),
                default => !$isOwning && !$isManyToMany
                    && in_array(constant(CascadeEnum::class . '::' . substr($kind, 9)), $columnSchema->cascade, true),
            };

            if ($matches) {
                $columns[] = $columnSchema;
            }
        }

        return $this->columnCache[$cacheKey] = $columns;
    }

    private function isNew(object $entity): bool
    {
        if ($this->isManaged($entity)) {
            return false;
        }

        $primaryColumn = $this->schemaProvider->getPrimaryColumnSchema($entity::class);

        // A detached entity with an auto-increment id already set is an existing row (updated in
        // full, as it has no snapshot). A key that is not auto-increment is the caller's to choose,
        // so an unmanaged entity with one is new.
        return !$primaryColumn->isAutoIncrement || !$this->schemaProvider->hasPrimaryKey($entity);
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
                    foreach ($chunk as $entity) {
                        $this->identityMap->add($entity, $this->schemaProvider->getPrimaryKey($entity));
                        $this->identityMap->setSnapshot($entity, $this->updatableValues($entity));
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
        if ($this->identityMap->get($entity::class, $primaryKey) === null) {
            $this->identityMap->add($entity, $primaryKey);
        }

        if ($this->identityMap->contains($entity, $primaryKey)) {
            $this->identityMap->setSnapshot($entity, $current);
        }
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
        foreach ($this->columnsOf($entity::class, 'manyToMany') as $columnSchema) {
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
        $pdo = $this->database->getPdo();
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
            $statement = $pdo->prepare('SELECT ' . $inverseColumnSql . ' FROM ' . $table . ' WHERE ' . $joinColumnSql . ' = ?');
            $statement->execute([$ownerId]);
            /** @var list<int|string> $existingIds */
            $existingIds = $statement->fetchAll(PDO::FETCH_COLUMN);
            foreach ($existingIds as $existingId) {
                $existing[$existingId] = $existingId;
            }
        }

        $toDelete = array_values(array_diff_key($existing, $wanted));
        if ($toDelete !== []) {
            $pdo->prepare(
                'DELETE FROM ' . $table . ' WHERE ' . $joinColumnSql . ' = ? AND ' . $inverseColumnSql
                . ' IN (' . implode(',', array_fill(0, count($toDelete), '?')) . ')',
            )->execute([$ownerId, ...$toDelete]);
        }

        $toInsert = array_values(array_diff_key($wanted, $existing));
        foreach (array_chunk($toInsert, max(1, intdiv(self::MaxParameters, 2))) as $chunk) {
            $parameters = [];
            foreach ($chunk as $relatedId) {
                array_push($parameters, $ownerId, $relatedId);
            }

            $pdo->prepare(
                'INSERT INTO ' . $table . ' (' . $joinColumnSql . ', ' . $inverseColumnSql . ') VALUES '
                . implode(',', array_fill(0, count($chunk), '(?, ?)')),
            )->execute($parameters);
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
                        $this->identityMap->remove($entity, $this->schemaProvider->getPrimaryKey($entity));
                    }
                }
            }
        }
    }

    /** Deletes the join rows that reference an entity, from either side of a ManyToMany relation. */
    private function deleteJoinRows(object $entity): void
    {
        $quoteChar = $this->database->getIdentifierQuoteChar();
        foreach ($this->columnsOf($entity::class, 'joinTables') as $columnSchema) {
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

            $this->database->getPdo()->prepare(
                'DELETE FROM ' . QuoteUtils::quote($joinTable, $quoteChar) . ' WHERE ' . QuoteUtils::quote($column, $quoteChar) . ' = ?',
            )->execute([$this->schemaProvider->getPrimaryKeyValue($entity)]);
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
