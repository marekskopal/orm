<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Attribute;

use Attribute;
use BackedEnum;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Column
{
    /** @param class-string<BackedEnum>|null $enum */
    public function __construct(
        public string $type,
        public ?string $name = null,
        public bool $primary = false,
        public bool $nullable = false,
        public ?string $enum = null,
    ) {
    }
}
