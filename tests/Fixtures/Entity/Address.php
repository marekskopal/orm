<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use Iterator;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Attribute\OneToMany;

#[Entity]
final class Address
{
    public function __construct(
        #[Column(type: 'int', primary: true, autoIncrement: true)]
        public int $id,
        #[Column(type: 'string')]
        public string $street,
        #[Column(type: 'string')]
        public string $city,
        #[ManyToOne(entityClass: CountryFixture::class)]
        public string $country,
        #[OneToMany(entityClass: UserFixture::class)]
        public Iterator $users,
    ) {
    }
}
