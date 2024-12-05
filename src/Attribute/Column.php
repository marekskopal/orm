<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Column
{
    public function __construct(public string $type, public ?string $name = null, public bool $primary = false)
    {
    }
}
