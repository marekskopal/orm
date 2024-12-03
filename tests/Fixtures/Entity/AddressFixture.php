<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;

final class AddressFixture
{
    public function __construct(
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'varchar(255)')]
        public string $street,
        #[Column(type: 'varchar(255)')]
        public string $city,
        #[Column(type: 'varchar(255)')]
        public string $country,
    ) {
    }
}
