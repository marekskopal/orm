<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Relation;

use Closure;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Entity\IdentityMap;
use MarekSkopal\ORM\Mapper\Collection;
use MarekSkopal\ORM\Query\Expression\RawExpression;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Utils\QuoteUtils;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use ReflectionProperty;
use WeakMap;

/**
 * Turns rows into entities and resolves their relations. Generated hydrators call back into it
 * for every relation property.
 *
 * - ManyToOne and OneToOne relations get one lazy proxy per related id. The proxy is registered in
 *   the identity map, so it is the canonical instance for that id and every parent shares it.
 * - Proxies initialise in batches: the first one to initialise loads every pending id of its class
 *   with one WHERE id IN (...) query; the others then initialise from the loaded rows.
 * - Collections (OneToMany, ManyToMany) stay lazy per parent unless preloaded with Select::with().
 * - with() loads every relation kind, including dotted paths, with one query per relation level.
 */
class RelationResolver
{
    private const int BatchSize = 1000;

    private const string OwnerColumn = '__orm_owner';

    private const string JoinAlias = '__orm_join';

    /** @var array<class-string, array<int|string, mixed>> key => id of uninitialised proxies whose row is not loaded yet */
    private array $pendingIds = [];

    /** @var array<class-string, array<int|string, array<string, mixed>>> loaded rows waiting for their proxy to initialise */
    private array $proxyRows = [];

    /** @var array<string, array<int|string, list<object>>> relation key => owner id => items loaded by with() */
    private array $preloadedCollections = [];

    /** @var array<string, array<int|string, object|null>> relation key => owner id => entity loaded by with() */
    private array $preloadedInverse = [];

    /** @var array<class-string, array<int|string, object>> the IdentityMap entities, bound by reference */
    private array $identityMap;

    /**
     * The IdentityMap snapshots, bound by reference. Public so generated hydrators record a snapshot
     * without a method call.
     *
     * @internal
     * @var array<int, list<mixed>|array<string, string|int|float|null>> object id => snapshot
     */
    public array $snapshots;

    private readonly IdentityMap $identityMapService;

    /** @var array<class-string, Closure(object): object> */
    private array $proxyFactories = [];

    /** @var WeakMap<object, mixed> proxy => id */
    private WeakMap $proxyIds;

    /** @var array<class-string, ReflectionClass<object>> */
    private array $reflectionClasses = [];

    /** @var array<class-string, ReflectionProperty> */
    private array $primaryProperties = [];

    /** @var array<class-string, Closure(object, string): mixed> */
    private array $propertyReaders = [];

    /** @var array<class-string, list<ColumnSchema>> inverse OneToOne relations whose property accepts null */
    private array $nullableInverseRelations = [];

    public function __construct(
        private readonly DatabaseInterface $database,
        private readonly SchemaProvider $schemaProvider,
        IdentityMap $identityMap,
    ) {
        $this->proxyIds = new WeakMap();
        $this->identityMap = &$identityMap->getEntitiesReference();
        $this->snapshots = &$identityMap->getSnapshotsReference();
        $this->identityMapService = $identityMap;
        $identityMap->onClear($this->reset(...));
    }

    /**
     * Returns the entity for a row: the identity-mapped instance if the id is known, otherwise a newly
     * hydrated one, which is then registered in the identity map.
     *
     * @param array<string, mixed> $row
     */
    public function hydrate(EntitySchema $entitySchema, array $row): object
    {
        $entityClass = $entitySchema->entityClass;
        $id = $this->getId($row, $entitySchema->getPrimaryColumn()->columnName);

        $entity = $this->identityMap[$entityClass][$id] ?? null;
        if ($entity !== null) {
            if (isset($this->pendingIds[$entityClass][$id])) {
                // The row is at hand, so the shared proxy for this id can initialise without a query.
                unset($this->pendingIds[$entityClass][$id]);
                $this->proxyRows[$entityClass][$id] = $row;
            }

            return $entity;
        }

        $entity = ($this->schemaProvider->getHydrator($entityClass))($row, $this);
        $this->identityMap[$entityClass][$id] = $entity;

        return $entity;
    }

    /**
     * hydrate() for many rows of one entity class, with the per-class lookups done once.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<object>
     */
    public function hydrateAll(EntitySchema $entitySchema, array $rows): array
    {
        $entityClass = $entitySchema->entityClass;
        $primaryColumnName = $entitySchema->getPrimaryColumn()->columnName;
        $hydrator = $this->schemaProvider->getHydrator($entityClass);

        $this->preloadNullableInverseRelations($entitySchema, $rows);

        // A reference to this class's slice of the identity map: one array lookup per row instead of two.
        // Nested hydrations (self-referencing relations) write through the same map, so it stays consistent.
        $this->identityMap[$entityClass] ??= [];
        $identityMap = &$this->identityMap[$entityClass];

        $entities = [];
        foreach ($rows as $row) {
            $id = $row[$primaryColumnName] ?? null;
            if (!is_int($id) && !is_string($id)) {
                $id = $this->getId($row, $primaryColumnName);
            }

            $entity = $identityMap[$id] ?? null;
            if ($entity === null) {
                $entity = $hydrator($row, $this);
                $identityMap[$id] = $entity;
            } elseif (isset($this->pendingIds[$entityClass][$id])) {
                unset($this->pendingIds[$entityClass][$id]);
                $this->proxyRows[$entityClass][$id] = $row;
            }

            $entities[] = $entity;
        }

        unset($identityMap);

        return $entities;
    }

    /**
     * Loads the given relation paths (e.g. "author", "posts.comments") for the parent rows before they
     * are hydrated. Each relation level costs one query regardless of the number of rows.
     *
     * @param list<string> $paths
     * @param list<array<string, mixed>> $rows
     */
    public function preload(EntitySchema $entitySchema, array $paths, array $rows): void
    {
        if ($rows === [] || $paths === []) {
            return;
        }

        /** @var array<string, list<string>> $tree */
        $tree = [];
        foreach ($paths as $path) {
            $parts = explode('.', $path, 2);
            $tree[$parts[0]] ??= [];
            if (isset($parts[1])) {
                $tree[$parts[0]][] = $parts[1];
            }
        }

        foreach ($tree as $propertyName => $nestedPaths) {
            $columnSchema = $entitySchema->columns[$propertyName] ?? throw new \InvalidArgumentException(
                sprintf('"%s" is not a property of entity "%s".', $propertyName, $entitySchema->entityClass),
            );

            match ($columnSchema->isRelation() ? $columnSchema->relationType : null) {
                RelationEnum::ManyToOne, RelationEnum::OneToOne => $this->preloadOwning($columnSchema, $nestedPaths, $rows),
                RelationEnum::OneToMany, RelationEnum::ManyToMany, RelationEnum::ManyToManyInverse => $this->preloadCollection(
                    $entitySchema,
                    $columnSchema,
                    $nestedPaths,
                    $rows,
                ),
                RelationEnum::OneToOneInverse => $this->preloadInverse($entitySchema, $columnSchema, $nestedPaths, $rows),
                null => throw new \InvalidArgumentException(sprintf(
                    'Property "%s" of entity "%s" is not a relation and cannot be loaded with with().',
                    $propertyName,
                    $entitySchema->entityClass,
                )),
            };
        }
    }

    /**
     * Resolves a ManyToOne or OneToOne relation to the identity-mapped entity, or to a shared lazy proxy.
     *
     * @template T of object
     * @param class-string<T> $entityClass
     * @param mixed $id the primary key value, already converted to the type of the primary key property
     * @return T
     */
    public function manyToOne(string $entityClass, mixed $id): object
    {
        $key = is_int($id) ? $id : IdentityMap::key($id);

        /** @var T|null $entity */
        $entity = $this->identityMap[$entityClass][$key] ?? null;

        return $entity ?? $this->createProxy($entityClass, $id, $key);
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return T
     */
    private function createProxy(string $entityClass, mixed $id, int|string $key): object
    {
        /** @var Closure(T): T $factory the hydrator returns an instance of the proxied class */
        $factory = $this->proxyFactories[$entityClass] ??= fn(object $proxy): object => $this->initialiseProxy($proxy, $entityClass);
        $proxy = $this->getReflectionClass($entityClass)->newLazyProxy($factory);

        // Seed the primary key so reading it (e.g. for the owner's foreign key column) does not initialise the proxy.
        $this->getPrimaryProperty($entityClass)->setRawValueWithoutLazyInitialization($proxy, $id);
        $this->proxyIds[$proxy] = $id;
        $this->pendingIds[$entityClass][$key] = $id;
        $this->identityMap[$entityClass][$key] = $proxy;

        return $proxy;
    }

    /**
     * Resolves a OneToMany, ManyToMany or inverse ManyToMany relation to its collection: the items
     * preloaded by with(), or a lazy collection that loads on first access.
     *
     * @param string $relationKey "OwnerClass::property"
     * @return Collection<object>
     */
    public function collection(string $relationKey, int|string $ownerId): Collection
    {
        if (isset($this->preloadedCollections[$relationKey][$ownerId])) {
            $items = $this->preloadedCollections[$relationKey][$ownerId];
            unset($this->preloadedCollections[$relationKey][$ownerId]);

            return new Collection($items);
        }

        return Collection::lazy($this, $relationKey, $ownerId);
    }

    /**
     * Loads the items of one lazy collection; called by the collection on first access.
     *
     * @internal used by Collection
     * @param string $relationKey "OwnerClass::property"
     * @return list<object>
     */
    public function loadCollection(string $relationKey, int|string $ownerId): array
    {
        // No preloaded entry to consume: with() fills the lazy collections of owners that exist already.
        $columnSchema = $this->getRelationColumnSchema($relationKey);
        $targetSchema = $this->schemaProvider->getEntitySchema($this->getRelationEntityClass($columnSchema));

        return $this->hydrateAll($targetSchema, $this->fetchCollectionRows($columnSchema, [$ownerId]));
    }

    /**
     * Resolves the inverse side of a OneToOne relation: the entity preloaded by with(), or a lazy
     * proxy that loads it on first access.
     *
     * @param string $relationKey "OwnerClass::property"
     */
    public function oneToOneInverse(string $relationKey, int|string $ownerId, bool $nullable): ?object
    {
        $key = $relationKey;
        $columnSchema = $this->getRelationColumnSchema($relationKey);
        $targetClass = $this->getRelationEntityClass($columnSchema);

        if (isset($this->preloadedInverse[$key]) && array_key_exists($ownerId, $this->preloadedInverse[$key])) {
            $entity = $this->preloadedInverse[$key][$ownerId];
            unset($this->preloadedInverse[$key][$ownerId]);
            if ($entity === null && !$nullable) {
                throw $this->inverseNotFound($targetClass, $ownerId);
            }

            return $entity;
        }

        $foreignKeyColumn = $this->getInverseForeignKeyColumn($columnSchema);

        // A proxy cannot turn into null, so a nullable relation is resolved now. Batch hydration
        // preloads these relations, so this query only runs for single hydrations.
        if ($nullable) {
            return $this->select($targetClass)->where([$foreignKeyColumn, '=', $ownerId])->fetchOne();
        }

        return $this->getReflectionClass($targetClass)->newLazyProxy(
            function () use ($targetClass, $foreignKeyColumn, $ownerId): object {
                $entity = $this->select($targetClass)->where([$foreignKeyColumn, '=', $ownerId])->fetchOne()
                    ?? throw $this->inverseNotFound($targetClass, $ownerId);

                return $this->unwrapProxy($entity);
            },
        );
    }

    /** @param class-string $entityClass */
    public function mapExtension(string $entityClass, string $propertyName, string|int|float|bool $value): mixed
    {
        return $this->schemaProvider->getExtensionMapperProvider()->mapToProperty($entityClass, $propertyName, $value);
    }

    /** Drops state tied to identity-mapped entities; runs when the entity cache is cleared. */
    private function reset(): void
    {
        $this->pendingIds = [];
        $this->proxyRows = [];
        $this->preloadedCollections = [];
        $this->preloadedInverse = [];
    }

    /** @param class-string $entityClass */
    private function initialiseProxy(object $proxy, string $entityClass): object
    {
        $id = $this->proxyIds[$proxy];
        $key = IdentityMap::key($id);
        if (!isset($this->proxyRows[$entityClass][$key])) {
            $this->loadPendingProxies($entityClass, $id, $key);
        }

        $row = $this->proxyRows[$entityClass][$key] ?? throw new \RuntimeException(
            sprintf('Entity "%s" with id "%s" not found', $entityClass, $key),
        );
        unset($this->proxyRows[$entityClass][$key]);

        // The proxy stays the canonical instance in the identity map; this object only backs it,
        // so the snapshot belongs to the proxy.
        $entity = ($this->schemaProvider->getHydrator($entityClass))($row, $this);
        $this->identityMapService->moveSnapshot($entity, $proxy);

        return $entity;
    }

    /**
     * Loads the rows of every pending proxy of a class with one query per batch.
     *
     * @param class-string $entityClass
     */
    private function loadPendingProxies(string $entityClass, mixed $id, int|string $key): void
    {
        $ids = $this->pendingIds[$entityClass] ?? [];
        $ids[$key] = $id;
        unset($this->pendingIds[$entityClass]);

        $primaryColumnName = $this->schemaProvider->getPrimaryColumnSchema($entityClass)->columnName;
        /** @var list<int|string|UuidInterface> $idValues */
        $idValues = array_values($ids);
        foreach (array_chunk($idValues, self::BatchSize) as $chunk) {
            foreach ($this->select($entityClass)->where([$primaryColumnName, 'IN', $chunk])->fetchAssocAll() as $row) {
                $this->proxyRows[$entityClass][$this->getId($row, $primaryColumnName)] = $row;
            }
        }
    }

    /**
     * @param list<string> $nestedPaths
     * @param list<array<string, mixed>> $rows
     */
    private function preloadOwning(ColumnSchema $columnSchema, array $nestedPaths, array $rows): void
    {
        $ids = [];
        foreach ($rows as $row) {
            $value = $row[$columnSchema->columnName] ?? null;
            if (is_int($value) || is_string($value)) {
                $ids[$value] = $value;
            }
        }

        $targetSchema = $this->schemaProvider->getEntitySchema($this->getRelationEntityClass($columnSchema));
        $primaryColumnName = $targetSchema->getPrimaryColumn()->columnName;

        // Entities already loaded need no query, unless nested paths need their rows.
        if ($nestedPaths === []) {
            $ids = array_filter($ids, fn(int|string $id): bool => $this->getHydrated($targetSchema->entityClass, $id) === null);
        }

        if ($ids === []) {
            return;
        }

        $relatedRows = [];
        foreach (array_chunk(array_values($ids), self::BatchSize) as $chunk) {
            array_push(
                $relatedRows,
                ...$this->select($targetSchema->entityClass)->where([$primaryColumnName, 'IN', $chunk])->fetchAssocAll(),
            );
        }

        $this->preload($targetSchema, $nestedPaths, $relatedRows);
        $this->hydrateAll($targetSchema, $relatedRows);
    }

    /**
     * @param list<string> $nestedPaths
     * @param list<array<string, mixed>> $rows
     */
    private function preloadCollection(EntitySchema $entitySchema, ColumnSchema $columnSchema, array $nestedPaths, array $rows): void
    {
        $ownerIds = $this->getIds($rows, $entitySchema->getPrimaryColumn()->columnName);
        $relatedRows = $this->fetchCollectionRows($columnSchema, $ownerIds);
        $targetSchema = $this->schemaProvider->getEntitySchema($this->getRelationEntityClass($columnSchema));

        $this->preload($targetSchema, $nestedPaths, $relatedRows);

        /** @var array<int|string, list<object>> $grouped */
        $grouped = array_fill_keys($ownerIds, []);
        foreach ($this->hydrateAll($targetSchema, $relatedRows) as $index => $related) {
            $grouped[$this->getId($relatedRows[$index], self::OwnerColumn)][] = $related;
        }

        $key = $entitySchema->entityClass . '::' . $columnSchema->propertyName;
        foreach ($grouped as $ownerId => $items) {
            $owner = $this->getHydrated($entitySchema->entityClass, $ownerId);
            if ($owner === null) {
                // Consumed when the owner is hydrated.
                $this->preloadedCollections[$key][$ownerId] = $items;
                continue;
            }

            // The owner exists already: fill its collection if it is still lazy, and store nothing,
            // so no entry outlives the call.
            $collection = $this->readProperty($owner, $columnSchema->propertyName);
            if ($collection instanceof Collection && !$collection->isInitialized()) {
                $collection->initializeWith($items);
            }
        }
    }

    /**
     * @param list<string> $nestedPaths
     * @param list<array<string, mixed>> $rows
     */
    private function preloadInverse(EntitySchema $entitySchema, ColumnSchema $columnSchema, array $nestedPaths, array $rows): void
    {
        $ownerIds = $this->getIds($rows, $entitySchema->getPrimaryColumn()->columnName);
        $targetSchema = $this->schemaProvider->getEntitySchema($this->getRelationEntityClass($columnSchema));
        $foreignKeyColumn = $this->getInverseForeignKeyColumn($columnSchema);

        $relatedRows = [];
        foreach (array_chunk($ownerIds, self::BatchSize) as $chunk) {
            array_push(
                $relatedRows,
                ...$this->select($targetSchema->entityClass)->where([$foreignKeyColumn, 'IN', $chunk])->fetchAssocAll(),
            );
        }

        $this->preload($targetSchema, $nestedPaths, $relatedRows);

        $related = [];
        foreach ($this->hydrateAll($targetSchema, $relatedRows) as $index => $entity) {
            $related[$this->getId($relatedRows[$index], $foreignKeyColumn)] = $entity;
        }

        // Only owners not hydrated yet consume an entry; an existing owner keeps its value, so no
        // entry outlives the call.
        $key = $entitySchema->entityClass . '::' . $columnSchema->propertyName;
        foreach ($ownerIds as $ownerId) {
            if ($this->getHydrated($entitySchema->entityClass, $ownerId) === null) {
                $this->preloadedInverse[$key][$ownerId] = $related[$ownerId] ?? null;
            }
        }
    }

    /**
     * Loads the nullable inverse OneToOne relations of rows about to be hydrated with one query per
     * relation, so the hydrator never queries them one row at a time.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function preloadNullableInverseRelations(EntitySchema $entitySchema, array $rows): void
    {
        $relations = $this->getNullableInverseRelations($entitySchema);
        if ($relations === [] || $rows === []) {
            return;
        }

        $primaryColumnName = $entitySchema->getPrimaryColumn()->columnName;
        foreach ($relations as $columnSchema) {
            $key = $entitySchema->entityClass . '::' . $columnSchema->propertyName;
            $pending = array_values(array_filter(
                $rows,
                fn(array $row): bool => !array_key_exists($this->getId($row, $primaryColumnName), $this->preloadedInverse[$key] ?? [])
                    && $this->getHydrated($entitySchema->entityClass, $this->getId($row, $primaryColumnName)) === null,
            ));

            if ($pending !== []) {
                $this->preloadInverse($entitySchema, $columnSchema, [], $pending);
            }
        }
    }

    /** @return list<ColumnSchema> */
    private function getNullableInverseRelations(EntitySchema $entitySchema): array
    {
        $entityClass = $entitySchema->entityClass;
        if (isset($this->nullableInverseRelations[$entityClass])) {
            return $this->nullableInverseRelations[$entityClass];
        }

        $relations = [];
        foreach ($entitySchema->columns as $columnSchema) {
            if ($columnSchema->relationType !== RelationEnum::OneToOneInverse) {
                continue;
            }

            $type = new ReflectionProperty($entityClass, $columnSchema->propertyName)->getType();
            if ($type === null || $type->allowsNull()) {
                $relations[] = $columnSchema;
            }
        }

        return $this->nullableInverseRelations[$entityClass] = $relations;
    }

    /**
     * The identity-mapped entity for an id if it is hydrated, i.e. present and not an uninitialised
     * proxy.
     *
     * @param class-string $entityClass
     */
    private function getHydrated(string $entityClass, int|string $id): ?object
    {
        $entity = $this->identityMap[$entityClass][$id] ?? null;
        if ($entity === null || $this->getReflectionClass($entityClass)->isUninitializedLazyObject($entity)) {
            return null;
        }

        return $entity;
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

    /**
     * Fetches the rows of a collection relation for several owners at once. Each row carries the
     * owner id in an extra column, so the rows can be grouped; ManyToMany joins the join table.
     *
     * @param list<int|string> $ownerIds
     * @return list<array<string, mixed>>
     */
    private function fetchCollectionRows(ColumnSchema $columnSchema, array $ownerIds): array
    {
        $targetSchema = $this->schemaProvider->getEntitySchema($this->getRelationEntityClass($columnSchema));
        $quoteChar = $this->database->getIdentifierQuoteChar();
        $targetPrimaryColumn = $targetSchema->getPrimaryColumn()->columnName;

        [$joinTable, $joinOnColumn, $ownerColumn] = match ($columnSchema->relationType) {
            RelationEnum::OneToMany => [
                null,
                null,
                $columnSchema->relationColumnName ?? throw new \LogicException('OneToMany relation has no relation column.'),
            ],
            RelationEnum::ManyToMany => [
                $columnSchema->joinTable ?? throw new \LogicException('ManyToMany relation has no join table.'),
                $columnSchema->inverseJoinColumn ?? throw new \LogicException('ManyToMany relation has no inverse join column.'),
                $columnSchema->joinColumn ?? throw new \LogicException('ManyToMany relation has no join column.'),
            ],
            RelationEnum::ManyToManyInverse => $this->getInverseJoin($targetSchema, $columnSchema),
            default => throw new \LogicException(sprintf('Relation "%s" is not a collection.', $columnSchema->propertyName)),
        };

        $ownerExpression = QuoteUtils::quote($joinTable === null ? $targetSchema->tableAlias : self::JoinAlias, $quoteChar)
            . '.' . QuoteUtils::quote($ownerColumn, $quoteChar);

        $rows = [];
        foreach (array_chunk($ownerIds, self::BatchSize) as $chunk) {
            $select = $this->select($targetSchema->entityClass);
            if ($joinTable !== null && $joinOnColumn !== null) {
                $select->join($targetPrimaryColumn, $joinTable, self::JoinAlias, $joinOnColumn);
            }

            $select->columns([
                ...array_keys($targetSchema->getSelectableColumns()),
                new RawExpression($ownerExpression . ' AS ' . QuoteUtils::quote(self::OwnerColumn, $quoteChar)),
            ])->where([new RawExpression($ownerExpression), 'IN', $chunk]);

            array_push($rows, ...$select->fetchAssocAll());
        }

        return $rows;
    }

    /** @return array{0: string, 1: string, 2: string} join table, join column matching the target id, owner column */
    private function getInverseJoin(EntitySchema $targetSchema, ColumnSchema $columnSchema): array
    {
        $owningColumnSchema = $targetSchema->getColumnByPropertyName(
            $columnSchema->mappedBy ?? throw new \LogicException('Inverse ManyToMany relation has no mappedBy.'),
        );

        return [
            $owningColumnSchema->joinTable ?? throw new \LogicException('Owning ManyToMany relation has no join table.'),
            $owningColumnSchema->joinColumn ?? throw new \LogicException('Owning ManyToMany relation has no join column.'),
            $owningColumnSchema->inverseJoinColumn ?? throw new \LogicException('Owning ManyToMany relation has no inverse join column.'),
        ];
    }

    private function getInverseForeignKeyColumn(ColumnSchema $columnSchema): string
    {
        return $this->schemaProvider->getEntitySchema($this->getRelationEntityClass($columnSchema))->getColumnByPropertyName(
            $columnSchema->mappedBy ?? throw new \LogicException('Inverse OneToOne relation has no mappedBy.'),
        )->columnName;
    }

    /** @param string $relationKey "OwnerClass::property" */
    private function getRelationColumnSchema(string $relationKey): ColumnSchema
    {
        [$ownerClass, $propertyName] = explode('::', $relationKey, 2) + [1 => ''];

        return $this->schemaProvider->getEntitySchema($ownerClass)->getColumnByPropertyName($propertyName);
    }

    /** @return class-string */
    private function getRelationEntityClass(ColumnSchema $columnSchema): string
    {
        return $columnSchema->relationEntityClass ?? throw new \LogicException(
            sprintf('Relation "%s" has no entity class.', $columnSchema->propertyName),
        );
    }

    /** @param class-string $targetClass */
    private function inverseNotFound(string $targetClass, int|string $ownerId): \RuntimeException
    {
        return new \RuntimeException(sprintf('OneToOne inverse entity "%s" not found for FK value "%s"', $targetClass, $ownerId));
    }

    /** A proxy factory must return a real object, so an identity-mapped proxy is replaced by its backing instance. */
    private function unwrapProxy(object $entity): object
    {
        $reflectionClass = $this->getReflectionClass($entity::class);
        if (!$reflectionClass->isUninitializedLazyObject($entity) && !isset($this->proxyIds[$entity])) {
            return $entity;
        }

        return $reflectionClass->initializeLazyObject($entity);
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Select<T>
     */
    private function select(string $entityClass): Select
    {
        return new Select(
            $this->database,
            $entityClass,
            $this->schemaProvider->getEntitySchema($entityClass),
            $this,
            $this->schemaProvider,
        );
    }

    /** @param array<string, mixed> $row */
    private function getId(array $row, string $columnName): int|string
    {
        $id = $row[$columnName] ?? null;
        if (!is_int($id) && !is_string($id)) {
            throw new \RuntimeException(sprintf('Column "%s" is missing from the row or is not an integer or string.', $columnName));
        }

        return $id;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int|string>
     */
    private function getIds(array $rows, string $columnName): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[$this->getId($row, $columnName)] = true;
        }

        return array_keys($ids);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return ReflectionClass<T>
     */
    private function getReflectionClass(string $class): ReflectionClass
    {
        /** @var ReflectionClass<T> $reflectionClass */
        $reflectionClass = $this->reflectionClasses[$class] ??= new ReflectionClass($class);

        return $reflectionClass;
    }

    /** @param class-string $entityClass */
    private function getPrimaryProperty(string $entityClass): ReflectionProperty
    {
        return $this->primaryProperties[$entityClass] ??= new ReflectionProperty(
            $entityClass,
            $this->schemaProvider->getPrimaryColumnSchema($entityClass)->propertyName,
        );
    }
}
