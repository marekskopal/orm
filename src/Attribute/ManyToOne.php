<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Attribute;

use Attribute;
use MarekSkopal\ORM\Schema\Enum\CascadeEnum;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToOne
{
    /**
     * @param class-string<object> $entityClass
     * @param list<CascadeEnum> $cascade
     */
    public function __construct(
        public string $entityClass,
        public ?string $name = null,
        public bool $nullable = false,
        public array $cascade = [],
    ) {
    }
}
