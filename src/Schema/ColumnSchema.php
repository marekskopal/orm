<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema;

use BackedEnum;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;

readonly class ColumnSchema
{
    /**
     * @param class-string<object>|null $relationEntityClass
     * @param class-string<BackedEnum>|null $enumClass
     */
    public function __construct(
        public string $propertyName,
        public PropertyTypeEnum $propertyType,
        public string $columnName,
        public string $columnType,
        public ?RelationEnum $relationType = null,
        public ?string $relationEntityClass = null,
        public bool $isPrimary = false,
        public bool $isAutoIncrement = false,
        public bool $isNullable = false,
        public ?string $enumClass = null,
    ) {
    }
}
