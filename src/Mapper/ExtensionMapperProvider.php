<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Mapper;

use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

/**
 * Holds one instance per extension mapper class and runs them for generated hydrators and
 * extractors, which only call into here for columns that declare an extension.
 */
class ExtensionMapperProvider
{
    /** @var array<class-string<MapperInterface>, MapperInterface> */
    private array $extensionMappers = [];

    public function __construct(private readonly ?SchemaProvider $schemaProvider = null)
    {
    }

    /** @param class-string $entityClass */
    public function mapToProperty(string $entityClass, string $propertyName, string|int|float|bool $value): mixed
    {
        $entitySchema = $this->getSchemaProvider()->getEntitySchema($entityClass);
        $columnSchema = $entitySchema->getColumnByPropertyName($propertyName);

        return $this->getExtensionMapper(
            $columnSchema->extensionClass ?? throw new \LogicException(sprintf('Property "%s" has no extension mapper.', $propertyName)),
        )->mapToProperty($entitySchema, $columnSchema, $value);
    }

    /** @param class-string $entityClass */
    public function mapToColumn(string $entityClass, string $propertyName, string|int|float|bool|object $value): string|int|float|null
    {
        $columnSchema = $this->getSchemaProvider()->getEntitySchema($entityClass)->getColumnByPropertyName($propertyName);

        return $this->getExtensionMapper(
            $columnSchema->extensionClass ?? throw new \LogicException(sprintf('Property "%s" has no extension mapper.', $propertyName)),
        )->mapToColumn($columnSchema, $value);
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
