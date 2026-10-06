<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema;

readonly class Schema
{
    /**
     * @template T of object
     * @param array<class-string<T>, EntitySchema> $entities
     */
    public function __construct(public array $entities)
    {
    }

    /**
     * Loads a schema written by SchemaBuilder::dump(). With opcache enabled the file is served from
     * shared memory, so no attribute scan, reflection or code generation runs at request time.
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Schema file "%s" does not exist.', $path));
        }

        $schema = require $path;
        if (!$schema instanceof self) {
            throw new \UnexpectedValueException(sprintf('Schema file "%s" does not return a %s.', $path, self::class));
        }

        return $schema;
    }
}
