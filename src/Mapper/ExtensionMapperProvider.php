<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Mapper;

class ExtensionMapperProvider
{
    /** @var array<class-string<MapperInterface>, MapperInterface> */
    private array $extensionMappers;

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
