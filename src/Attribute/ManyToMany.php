<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany
{
    /**
     * @param class-string<object> $entityClass
     * @param string|null $joinTable Join table name (owning side only)
     * @param string|null $joinColumn FK column in join table pointing to this entity's PK (owning side only)
     * @param string|null $inverseJoinColumn FK column in join table pointing to the related entity's PK (owning side only)
     * @param string|null $mappedBy Property name on the owning entity (inverse side only)
     */
    public function __construct(
        public string $entityClass,
        public ?string $joinTable = null,
        public ?string $joinColumn = null,
        public ?string $inverseJoinColumn = null,
        public ?string $mappedBy = null,
    ) {
    }
}
