<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Entity
{
    /** @param class-string|null $repositoryClass */
    public function __construct(public ?string $table = null, public ?string $repositoryClass = null)
    {
    }
}
