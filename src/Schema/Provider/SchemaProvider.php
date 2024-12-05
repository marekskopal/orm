<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Provider;

use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Schema;

readonly class SchemaProvider
{
    public function __construct(private Schema $schema)
    {
    }

    public function getEntitySchema(string $entityClass): EntitySchema
    {
        return $this->schema->entities[$entityClass] ?? throw new \InvalidArgumentException('Entity schema not found.');
    }

    public function getPrimaryColumnSchema(string $entityClass): ColumnSchema
    {
        return array_find($this->getEntitySchema($entityClass)->columns, fn(ColumnSchema $column): bool => $column->isPrimary)
            ?? throw new \InvalidArgumentException('Primary column schema not found.');
    }
}
