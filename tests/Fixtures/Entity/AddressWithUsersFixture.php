<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Mapper\Collection;

#[Entity(table: 'addresses')]
final class AddressWithUsersFixture
{
    /** @param Collection<UserWithAddressFixture> $users */
    public function __construct(
        #[Column(type: Type::Int, primary: true, autoIncrement: true)]
        public int $id,
        #[Column(type: Type::String)]
        public string $street,
        #[Column(type: Type::String)]
        public string $city,
        #[Column(type: Type::String)]
        public string $country,
        #[OneToMany(entityClass: UserWithAddressFixture::class, relationColumnName: 'address_id')]
        public Collection $users,
    ) {
    }
}
