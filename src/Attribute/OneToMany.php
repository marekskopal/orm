<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class OneToMany
{
    /** @param class-string<object> $entityClass */
    public function __construct(public string $entityClass)
    {
    }
}
