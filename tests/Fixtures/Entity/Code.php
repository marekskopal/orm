<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Enum\Type;
use Ramsey\Uuid\UuidInterface;

#[Entity]
final class Code
{
    public function __construct(#[Column(type: Type::Int, primary: true)] public int $id, #[Column(type: Type::Uuid)] public UuidInterface $code,)
    {
    }
}
