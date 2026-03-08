<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Attribute;

use Attribute;
use MarekSkopal\ORM\Schema\Enum\CascadeEnum;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToOne
{
    /**
     * @param class-string<object> $entityClass
     * @param string|null $name Custom FK column name (owning side only)
     * @param bool $nullable Whether the FK can be null (owning side only)
     * @param string|null $mappedBy Property name on the owning entity (inverse side only)
     * @param list<CascadeEnum> $cascade
     */
    public function __construct(
        public string $entityClass,
        public ?string $name = null,
        public bool $nullable = false,
        public ?string $mappedBy = null,
        public array $cascade = [],
    ) {
    }
}
