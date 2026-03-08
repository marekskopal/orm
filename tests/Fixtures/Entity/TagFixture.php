<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToMany;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Mapper\Collection;
use MarekSkopal\ORM\Repository\Repository;

#[Entity(table: 'tags', repositoryClass: Repository::class)]
class TagFixture
{
    #[Column(type: Type::Int, primary: true, autoIncrement: true)]
    public int $id;

    public function __construct(
        #[Column(type: Type::String)]
        public string $name,
        /** @var Collection<UserWithTagsFixture> */
        #[ManyToMany(entityClass: UserWithTagsFixture::class, mappedBy: 'tags')]
        public Collection $users,
    ) {
    }
}
