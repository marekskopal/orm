<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class ManyToOne
{
    /** @param class-string<object> $entityClass */
    public function __construct(public string $entityClass, public ?string $name = null,)
    {
    }
}
