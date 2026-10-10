<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\InvalidEntity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Enum\Type;

#[Entity]
final class UnionTypeEntity
{
    public function __construct(
        #[Column(type: Type::Int, primary: true)]
        public int $id,
        #[Column(type: Type::String)]
        public int|string $value,
    ) {
    }
}
