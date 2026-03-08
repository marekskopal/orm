<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToMany;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Mapper\Collection;
use MarekSkopal\ORM\Repository\Repository;

#[Entity(table: 'users', repositoryClass: Repository::class)]
class UserWithTagsFixture
{
    #[Column(type: Type::Int, primary: true, autoIncrement: true)]
    public int $id;

    public function __construct(
        #[Column(type: Type::String)]
        public string $name,
        /** @var Collection<TagFixture> */
        #[ManyToMany(entityClass: TagFixture::class, joinTable: 'user_tags', joinColumn: 'user_id', inverseJoinColumn: 'tag_id')]
        public Collection $tags,
    ) {
    }
}
