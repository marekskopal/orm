<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Attribute;

use Attribute;
use BackedEnum;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Mapper\MapperInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    // phpcs:disable
    public Type $type {
        get {
            return $this->type;
        }
        set(Type|string $value) {
            if (is_string($value)) {
                $value = Type::from($value);
            }
            $this->type = $value;
        }
    }
    // phpcs:enable

    /**
     * @param class-string<BackedEnum>|null $enum
     * @param class-string<MapperInterface>|null $extension
     * @param array<string, mixed> $extensionOptions
     */
    // phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable
    public function __construct(
        Type|string $type,
        public ?string $name = null,
        public bool $primary = false,
        public bool $nullable = false,
        public bool $autoIncrement = false,
        public ?int $size = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public string|int|float|bool|BackedEnum|null $default = null,
        public ?string $enum = null,
        public ?string $extension = null,
        public array $extensionOptions = [],
    ) {
        $this->type = $type;
    }
}
