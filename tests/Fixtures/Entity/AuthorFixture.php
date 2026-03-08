<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Mapper\Collection;
use MarekSkopal\ORM\Repository\Repository;
use MarekSkopal\ORM\Schema\Enum\CascadeEnum;

#[Entity(table: 'authors', repositoryClass: Repository::class)]
class AuthorFixture
{
    #[Column(type: Type::Int, primary: true, autoIncrement: true)]
    public int $id;

    public function __construct(
        #[Column(type: Type::String)]
        public string $name,
        /** @var Collection<PostFixture> */
        #[OneToMany(entityClass: PostFixture::class, relationColumnName: 'author_id', cascade: [CascadeEnum::Persist, CascadeEnum::Remove])]
        public Collection $posts,
    ) {
    }
}
