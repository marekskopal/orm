<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Entity;

use ReflectionClass;
use ReflectionParameter;

class EntityReflection
{
    /** @var array<class-string, array<ReflectionParameter>> */
    private array $parameters = [];

    /**
     * @param class-string $entityClass
     * @return array<ReflectionParameter>
     */
    public function getParameters(string $entityClass): array
    {
        if (isset($this->parameters[$entityClass])) {
            return $this->parameters[$entityClass];
        }

        $reflectionClass = new ReflectionClass($entityClass);
        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            throw new \RuntimeException('Entity must have constructor');
        }

        $parameters = $constructor->getParameters();
        $this->parameters[$entityClass] = $parameters;

        return $parameters;
    }
}
