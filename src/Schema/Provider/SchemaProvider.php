<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Provider;

use Closure;
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

    public function getExtensionMapperProvider(): ExtensionMapperProvider
    {
        return $this->extensionMapperProvider ??= new ExtensionMapperProvider($this);
    }

    private function getCompiler(): SchemaCompiler
    {
        return $this->compiler ??= new SchemaCompiler($this);
    }
}
