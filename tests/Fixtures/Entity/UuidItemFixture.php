<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Repository\Repository;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'uuid_items', repositoryClass: Repository::class)]
class UuidItemFixture
{
    public function __construct(
        #[Column(type: Type::Uuid, primary: true)]
        public UuidInterface $id,
        #[Column(type: Type::String)]
        public string $name,
    ) {
    }
}
