<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Enum\Type;

#[Entity(table: 'countries')]
final class CountryFixture
{
    public function __construct(
        #[Column(type: Type::Int, primary: true, autoIncrement: true)]
        public int $id,
        #[Column(type: Type::String)]
        public string $name,
    ) {
    }
}
