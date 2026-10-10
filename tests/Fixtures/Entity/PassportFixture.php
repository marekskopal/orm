<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\OneToOne;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Repository\Repository;

#[Entity(table: 'passports', repositoryClass: Repository::class)]
class PassportFixture
{
    #[Column(type: Type::Int, primary: true, autoIncrement: true)]
    public int $id;

    public function __construct(
        #[Column(type: Type::String)]
        public string $number,
        #[OneToOne(entityClass: CitizenFixture::class)]
        public CitizenFixture $citizen,
    ) {
    }
}
