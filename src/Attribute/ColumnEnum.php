<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Attribute;

use Attribute;
use BackedEnum;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ColumnEnum extends Column
{
    /** @param class-string<BackedEnum>|null $enum */
    public function __construct(
        public ?string $enum,
        public ?string $name = null,
        public bool $nullable = false,
        public mixed $default = null,
    )
    {
        parent::__construct(type: 'enum', name: $name, nullable: $nullable, default: $default);
    }
}
