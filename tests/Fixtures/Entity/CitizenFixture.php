<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\OneToOne;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Repository\Repository;

/** The inverse side of a non-nullable OneToOne: it resolves to a lazy proxy instead of a query. */
#[Entity(table: 'citizens', repositoryClass: Repository::class)]
class CitizenFixture
{
    #[Column(type: Type::Int, primary: true, autoIncrement: true)]
    public int $id;

    public function __construct(
        #[Column(type: Type::String)]
        public string $name,
        #[OneToOne(entityClass: PassportFixture::class, mappedBy: 'citizen')]
        public PassportFixture $passport,
    ) {
    }
}
