<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema;

use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;

readonly class ColumnSchema
{
    public function __construct(
        public string $propertyName,
        public PropertyTypeEnum $propertyType,
        public string $columnName,
        public string $columnType,
        public bool $isPrimary = false,
        public bool $isAutoIncrement = false,
    ) {
    }
}
