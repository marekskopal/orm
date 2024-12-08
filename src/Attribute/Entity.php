<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Attribute;

use Attribute;
use MarekSkopal\ORM\Repository\RepositoryInterface;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Entity
{
    /** @param class-string<RepositoryInterface<covariant object>>|null $repositoryClass */
    public function __construct(public ?string $table = null, public ?string $repositoryClass = null)
    {
    }
}
