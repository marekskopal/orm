<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema;

use BackedEnum;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Mapper\MapperInterface;
use MarekSkopal\ORM\Schema\Enum\CascadeEnum;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;

readonly class ColumnSchema
{
    /**
     * @param class-string<object>|null $relationEntityClass
     * @param class-string<BackedEnum>|null $enumClass
     * @param class-string<MapperInterface>|null $extensionClass
     * @param array<string, mixed> $extensionOptions
     * @param list<CascadeEnum> $cascade
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
        public ?string $joinTable = null,
        public ?string $joinColumn = null,
        public ?string $inverseJoinColumn = null,
        public ?string $mappedBy = null,
        public array $cascade = [],
    ) {
    }

    /**
     * Whether the property holds related entities. A scalar #[Column] with #[ForeignKey] carries a
     * relation type and entity class for the FK constraint, but its property holds the raw key.
     */
    public function isRelation(): bool
    {
        return $this->propertyType === PropertyTypeEnum::Relation;
    }

    /** A ManyToOne or OneToOne relation, whose property holds the related entity and whose column its key. */
    public function isOwningRelation(): bool
    {
        return $this->isRelation() && ($this->relationType === RelationEnum::ManyToOne || $this->relationType === RelationEnum::OneToOne);
    }
}
