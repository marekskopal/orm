<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Repository\Repository;

#[Entity(table: 'articles', repositoryClass: Repository::class)]
class ArticleFixture
{
    #[Column(type: Type::Int, primary: true, autoIncrement: true, name: 'article_id')]
    public int $id;

    public function __construct(#[Column(type: Type::String)] public string $title,)
    {
    }
}
