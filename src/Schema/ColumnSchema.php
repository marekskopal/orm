<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema;

use BackedEnum;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Mapper\MapperInterface;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;

readonly class ColumnSchema
{
    /**
     * @param class-string<object>|null $relationEntityClass
     * @param class-string<BackedEnum>|null $enumClass
     * @param class-string<MapperInterface>|null $extensionClass
     * @param array<string, mixed> $extensionOptions
     */
    public function __construct(
        public string $propertyName,
        public PropertyTypeEnum $propertyType,
        public string $columnName,
        public Type $columnType,
        public ?RelationEnum $relationType = null,
        public ?string $relationEntityClass = null,
        public ?string $relationColumnName = null,
        public bool $isPrimary = false,
        public bool $isAutoIncrement = false,
        public bool $isNullable = false,
        public ?int $size = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public string|int|float|bool|null|BackedEnum $default = null,
        public ?string $enumClass = null,
        public ?string $extensionClass = null,
        public array $extensionOptions = [],
    ) {
    }
}
