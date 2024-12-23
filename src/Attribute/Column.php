<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Attribute;

use Attribute;
use BackedEnum;
use MarekSkopal\ORM\Mapper\MapperInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    /**
     * @param class-string<BackedEnum>|null $enum
     * @param class-string<MapperInterface>|null $extension
     * @param array<string, mixed> $extensionOptions
     */
    public function __construct(
        public string $type,
        public ?string $name = null,
        public bool $primary = false,
        public bool $nullable = false,
        public ?int $size = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public mixed $default = null,
        public ?string $enum = null,
        public ?string $extension = null,
        public array $extensionOptions = [],
    ) {
    }
}
