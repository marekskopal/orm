<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use Iterator;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\OneToMany;

#[Entity(table: 'addresses')]
final class AddressWithUsersFixture
{
    public function __construct(
        #[Column(type: 'int', primary: true)]
        public int $id,
        #[Column(type: 'varchar(255)')]
        public string $street,
        #[Column(type: 'varchar(255)')]
        public string $city,
        #[Column(type: 'varchar(255)')]
        public string $country,
        #[OneToMany(entityClass: UserFixture::class)]
        public Iterator $users,
    ) {
    }
}
