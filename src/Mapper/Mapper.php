<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Mapper;

use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;

class Mapper
{
    public function mapColumn(ColumnSchema $schema, string|int|float $value): string|int|float|bool
    {
        return match ($schema->propertyType) {
            PropertyTypeEnum::String => (string) $value,
            PropertyTypeEnum::Int => (int) $value,
            PropertyTypeEnum::Float => (float) $value,
            PropertyTypeEnum::Bool => (bool) $value,
        };
    }
}
