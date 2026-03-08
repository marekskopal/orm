<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\OneToOne;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Repository\Repository;

#[Entity(table: 'profiles', repositoryClass: Repository::class)]
class ProfileFixture
{
    #[Column(type: Type::Int, primary: true, autoIncrement: true)]
    public int $id;

    public function __construct(
        #[Column(type: Type::String)]
        public string $bio,
        #[OneToOne(entityClass: UserWithProfileFixture::class, mappedBy: 'profile')]
        public ?UserWithProfileFixture $user,
    ) {
    }
}
