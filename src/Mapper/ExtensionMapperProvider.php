<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Mapper;

use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

/**
 * Holds one instance per extension mapper class and runs them for generated hydrators and
 * extractors, which only call into here for columns that declare an extension.
 */
class ExtensionMapperProvider
{
    /** @var array<class-string<MapperInterface>, MapperInterface> */
    private array $extensionMappers = [];

    /** @var array<string, array{0: EntitySchema, 1: ColumnSchema, 2: MapperInterface}> "class::property" => resolved mapping */
    private array $mappings = [];

    public function __construct(private readonly ?SchemaProvider $schemaProvider = null)
    {
    }

    /** @param class-string $entityClass */
    public function mapToProperty(string $entityClass, string $propertyName, string|int|float|bool $value): mixed
    {
        [$entitySchema, $columnSchema, $mapper] = $this->mappings[$entityClass . '::' . $propertyName]
            ?? $this->resolveMapping($entityClass, $propertyName);

        return $mapper->mapToProperty($entitySchema, $columnSchema, $value);
    }

    /** @param class-string $entityClass */
    public function mapToColumn(string $entityClass, string $propertyName, string|int|float|bool|object $value): string|int|float|null
    {
        [, $columnSchema, $mapper] = $this->mappings[$entityClass . '::' . $propertyName]
            ?? $this->resolveMapping($entityClass, $propertyName);

        return $mapper->mapToColumn($columnSchema, $value);
    }

    /**
     * Looks up the schemas and mapper of an extension column once; generated code calls the
     * mapping methods for every row.
     *
     * @param class-string $entityClass
     * @return array{0: EntitySchema, 1: ColumnSchema, 2: MapperInterface}
     */
    private function resolveMapping(string $entityClass, string $propertyName): array
    {
        $entitySchema = $this->getSchemaProvider()->getEntitySchema($entityClass);
        $columnSchema = $entitySchema->getColumnByPropertyName($propertyName);
        $mapper = $this->getExtensionMapper(
            $columnSchema->extensionClass ?? throw new \LogicException(sprintf('Property "%s" has no extension mapper.', $propertyName)),
        );

        return $this->mappings[$entityClass . '::' . $propertyName] = [$entitySchema, $columnSchema, $mapper];
    }

    private function getSchemaProvider(): SchemaProvider
    {
        return $this->schemaProvider ?? throw new \LogicException('ExtensionMapperProvider was created without a SchemaProvider.');
    }

    /** @param class-string<MapperInterface> $extensionClass */
    public function getExtensionMapper(string $extensionClass): MapperInterface
    {
        if (isset($this->extensionMappers[$extensionClass])) {
            return $this->extensionMappers[$extensionClass];
        }

        $this->extensionMappers[$extensionClass] = new $extensionClass();

        return $this->extensionMappers[$extensionClass];
    }
}
