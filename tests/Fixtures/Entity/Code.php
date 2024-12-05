<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use Ramsey\Uuid\UuidInterface;

#[Entity]
final class Code
{
    public function __construct(#[Column(type: 'int', primary: true)] public int $id, #[Column(type: 'uuid')] public UuidInterface $code,)
    {
    }
}
