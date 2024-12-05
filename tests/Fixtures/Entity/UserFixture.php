<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Tests\Fixtures\Repository\UserRepositoryFixture;

#[Entity(table: 'users', repositoryClass: UserRepositoryFixture::class)]
final class UserFixture
{
    public function __construct(
        #[Column(type: 'int', primary: true)]
        public int $id,
        #[Column(type: 'varchar(255)')]
        public string $firstName,
        #[Column(type: 'varchar(255)')]
        public string $lastName,
        #[Column(type: 'varchar(255)')]
        public string $email,
        #[Column(type: 'tinyint(1)')]
        public bool $isActive,
    ) {
    }

    public static function create(
        int $id = 1,
        string $firstName = 'John',
        string $lastName = 'Doe',
        string $email = 'john.doe@example.com',
        bool $isActive = true,
    ): self {
        return new self(id: $id, firstName: $firstName, lastName: $lastName, email: $email, isActive: $isActive);
    }
}
