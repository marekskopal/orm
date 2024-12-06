<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Entity;

use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;

class EntityReflection
{
    /** @var array<class-string, array<ReflectionProperty>> */
    private array $properties = [];

    /** @var array<class-string, array<ReflectionParameter>> */
    private array $constructorParameters = [];

    /** @var array<class-string, array<ReflectionProperty>> */
    private array $propertiesNotInConstructor = [];

    /**
     * @param class-string $entityClass
     * @return array<ReflectionProperty>
     */
    public function getProperties(string $entityClass): array
    {
        if (isset($this->properties[$entityClass])) {
            return $this->properties[$entityClass];
        }

        $reflectionClass = new ReflectionClass($entityClass);

        $properties = $reflectionClass->getProperties();
        $this->properties[$entityClass] = $properties;

        return $properties;
    }

    /**
     * @param class-string $entityClass
     * @return array<ReflectionParameter>
     */
    public function getConstructorParameters(string $entityClass): array
    {
        if (isset($this->constructorParameters[$entityClass])) {
            return $this->constructorParameters[$entityClass];
        }

        $reflectionClass = new ReflectionClass($entityClass);
        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            throw new \RuntimeException('Entity must have constructor');
        }

        $constructorParameters = $constructor->getParameters();
        $this->constructorParameters[$entityClass] = $constructorParameters;

        return $constructorParameters;
    }

    /**
     * @param class-string $entityClass
     * @return array<ReflectionProperty>
     */
    public function getPropertiesNotInConstructor(string $entityClass): array
    {
        if (isset($this->propertiesNotInConstructor[$entityClass])) {
            return $this->propertiesNotInConstructor[$entityClass];
        }

        $constructorParameters = $this->getConstructorParameters($entityClass);

        $propertiesNotInConstructor = array_filter(
            $this->getProperties($entityClass),
            fn(ReflectionProperty $property) => !array_any(
                $constructorParameters,
                fn(ReflectionParameter $constructorParameter) => $constructorParameter->getName() === $property->getName(),
            ),
        );

        $this->propertiesNotInConstructor[$entityClass] = $propertiesNotInConstructor;

        return $propertiesNotInConstructor;
    }
}
