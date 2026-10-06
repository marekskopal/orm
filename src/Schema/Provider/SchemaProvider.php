<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Provider;

use Closure;
use MarekSkopal\ORM\Entity\IdentityMap;
use MarekSkopal\ORM\Mapper\ExtensionMapperProvider;
use MarekSkopal\ORM\Relation\RelationResolver;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Compiler\SchemaCompiler;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Schema;

class SchemaProvider
{
    /** @var array<class-string, Closure(array<string, mixed>, RelationResolver): object> */
    private array $hydrators = [];

    /** @var array<class-string, Closure(object, ExtensionMapperProvider): array<string, string|int|float|null>> */
    private array $extractors = [];

    /** @var array<class-string, Closure(list<mixed>, ExtensionMapperProvider): array<string, string|int|float|null>> */
    private array $normalizers = [];

    /** @var array<class-string, Closure(object): mixed> */
    private array $primaryKeyReaders = [];

    /** @var array<class-string, Closure(object, mixed): void> */
    private array $primaryKeyWriters = [];

    /** @var array<class-string, Closure(object): bool> */
    private array $primaryKeyCheckers = [];

    /** @var array<class-string, Closure(object): void> */
    private array $primaryKeyClearers = [];

    private ?SchemaCompiler $compiler = null;

    private ?ExtensionMapperProvider $extensionMapperProvider = null;

    public function __construct(private readonly Schema $schema)
    {
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function getEntitySchema(string $entityClass): EntitySchema
    {
        return $this->schema->entities[$entityClass] ?? throw new \InvalidArgumentException('Entity schema not found.');
    }

    public function getPrimaryColumnSchema(string $entityClass): ColumnSchema
    {
        return $this->getEntitySchema($entityClass)->getPrimaryColumn();
    }

    /**
     * Returns the generated hydrator for an entity: the one loaded from a dumped schema file,
     * or one compiled from the schema on first use.
     *
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Closure(array<string, mixed>, RelationResolver): T
     */
    public function getHydrator(string $entityClass): Closure
    {
        /** @var Closure(array<string, mixed>, RelationResolver): T $hydrator */
        $hydrator = $this->hydrators[$entityClass] ??= $this->getEntitySchema($entityClass)->hydrator
            ?? $this->getCompiler()->compileHydrator($entityClass);

        return $hydrator;
    }

    /**
     * @param class-string $entityClass
     * @return Closure(object, ExtensionMapperProvider): array<string, string|int|float|null>
     */
    public function getExtractor(string $entityClass): Closure
    {
        return $this->extractors[$entityClass] ??= $this->getEntitySchema($entityClass)->extractor
            ?? $this->getCompiler()->compileExtractor($entityClass);
    }

    /**
     * Converts an entity to database values for its insertable columns, keyed by column name.
     *
     * @return array<string, string|int|float|null>
     */
    public function extract(object $entity): array
    {
        return ($this->getExtractor($entity::class))($entity, $this->getExtensionMapperProvider());
    }

    /**
     * @param class-string $entityClass
     * @return Closure(list<mixed>, ExtensionMapperProvider): array<string, string|int|float|null>
     */
    public function getNormalizer(string $entityClass): Closure
    {
        return $this->normalizers[$entityClass] ??= $this->getEntitySchema($entityClass)->normalizer
            ?? $this->getCompiler()->compileNormalizer($entityClass);
    }

    /**
     * Converts a hydration snapshot to the extractor's format, so it can be compared with extract().
     *
     * @param list<mixed> $snapshot
     * @return array<string, string|int|float|null>
     */
    public function normalizeSnapshot(object $entity, array $snapshot): array
    {
        return ($this->getNormalizer($entity::class))($snapshot, $this->getExtensionMapperProvider());
    }

    /** The primary key property value, read in the entity's scope so private keys work. */
    public function getPrimaryKey(object $entity): mixed
    {
        $entityClass = $entity::class;
        $reader = $this->primaryKeyReaders[$entityClass] ??= $this->bindToEntity(
            $entityClass,
            // @phpstan-ignore-next-line property.dynamicName
            static fn(string $property): Closure => static fn(object $entity): mixed => $entity->{$property},
        );

        return $reader($entity);
    }

    public function hasPrimaryKey(object $entity): bool
    {
        $entityClass = $entity::class;
        $checker = $this->primaryKeyCheckers[$entityClass] ??= $this->bindToEntity(
            $entityClass,
            // @phpstan-ignore-next-line property.dynamicName
            static fn(string $property): Closure => static fn(object $entity): bool => isset($entity->{$property}),
        );

        return $checker($entity);
    }

    public function setPrimaryKey(object $entity, mixed $value): void
    {
        $entityClass = $entity::class;
        $writer = $this->primaryKeyWriters[$entityClass] ??= $this->bindToEntity(
            $entityClass,
            static fn(string $property): Closure => static function (object $entity, mixed $value) use ($property): void {
                // @phpstan-ignore-next-line property.dynamicName
                $entity->{$property} = $value;
            },
        );

        $writer($entity, $value);
    }

    /** Unsets the primary key property, e.g. to undo an id assigned by an insert that was rolled back. */
    public function clearPrimaryKey(object $entity): void
    {
        $entityClass = $entity::class;
        $clearer = $this->primaryKeyClearers[$entityClass] ??= $this->bindToEntity(
            $entityClass,
            static fn(string $property): Closure => static function (object $entity) use ($property): void {
                // @phpstan-ignore-next-line property.dynamicName
                unset($entity->{$property});
            },
        );

        $clearer($entity);
    }

    /** The primary key as a database value: integers and strings as they are, UUIDs and other objects as strings. */
    public function getPrimaryKeyValue(object $entity): int|string
    {
        $value = $this->getPrimaryKey($entity);

        return is_int($value) ? $value : IdentityMap::key($value);
    }

    /**
     * @template TClosure of Closure
     * @param class-string $entityClass
     * @param Closure(string): TClosure $factory builds the accessor for the primary key property name
     * @return TClosure
     */
    private function bindToEntity(string $entityClass, Closure $factory): Closure
    {
        $accessor = $factory($this->getPrimaryColumnSchema($entityClass)->propertyName);

        /** @var TClosure $bound */
        $bound = Closure::bind($accessor, null, $entityClass) ?? throw new \LogicException('Cannot bind to ' . $entityClass);

        return $bound;
    }

    public function getExtensionMapperProvider(): ExtensionMapperProvider
    {
        return $this->extensionMapperProvider ??= new ExtensionMapperProvider($this);
    }

    private function getCompiler(): SchemaCompiler
    {
        return $this->compiler ??= new SchemaCompiler($this);
    }
}
