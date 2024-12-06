<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Tests\Fixtures\Repository\UserRepositoryFixture;

#[Entity(table: 'users', repositoryClass: UserRepositoryFixture::class)]
final class UserFixture
{
    #[Column(type: 'int', primary: true)]
    public int $id;

    public function __construct(
        #[Column(type: 'varchar(255)')]
        public string $firstName,
        #[Column(type: 'varchar(255)', nullable: true)]
        public ?string $middleName,
        #[Column(type: 'varchar(255)')]
        public string $lastName,
        #[Column(type: 'varchar(255)')]
        public string $email,
        #[Column(type: 'tinyint(1)')]
        public bool $isActive,
    ) {
    }

    public static function create(
        ?string $firstName = null,
        ?string $middleName = null,
        ?string $lastName = null,
        ?string $email = null,
        ?bool $isActive = true,
    ): self {
        return new self(
            firstName: $firstName ?? 'John',
            middleName: $middleName,
            lastName: $lastName ?? 'Doe',
            email: $email ?? 'john.doe@example.com',
            isActive: $isActive ?? true,
        );
    }
}
