<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ManyToOne;

class UserWithAddressFixture
{
    public function __construct(
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'varchar(255)')]
        public string $firstName,
        #[Column(type: 'varchar(255)')]
        public string $lastName,
        #[Column(type: 'varchar(255)')]
        public string $email,
        #[Column(type: 'tinyint(1)')]
        public bool $isActive,
        #[ManyToOne(entityClass: AddressFixture::class)]
        public AddressFixture $address,
    ) {
    }
}
