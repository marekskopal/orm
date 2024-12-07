<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Mapper;

use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;

interface MapperInterface
{
    public function mapToProperty(
        EntitySchema $entitySchema,
        ColumnSchema $columnSchema,
        string|int|float|null $value,
    ): string|int|float|bool|object|null;

    public function mapToColumn(ColumnSchema $columnSchema, string|int|float|bool|object|null $value): string|int|float|null;
}
