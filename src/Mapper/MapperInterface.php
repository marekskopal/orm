<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Mapper;

use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;

interface MapperInterface
{
    /**
     * Maps a raw database value to a property value. Drivers may return native booleans
     * (pdo_pgsql does for BOOLEAN columns), so implementations must accept bool.
     */
    public function mapToProperty(
        EntitySchema $entitySchema,
        ColumnSchema $columnSchema,
        string|int|float|bool|null $value,
    ): string|int|float|bool|object|null;

    public function mapToColumn(ColumnSchema $columnSchema, string|int|float|bool|object|null $value): string|int|float|null;
}
