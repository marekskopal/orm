<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Repository\UserRepositoryFixture;

#[Entity(table: 'users', repositoryClass: UserRepositoryFixture::class)]
final class UserFixture
{
    #[Column(type: 'int', primary: true)]
    public int $id;

    public function __construct(
        #[Column(type: 'timestamp')]
        public DateTimeImmutable $createdAt,
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
        #[Column(type: 'enum', enum: UserTypeEnum::class)]
        public UserTypeEnum $type,
    ) {
    }

    public static function create(
        ?DateTimeImmutable $createdAt = null,
        ?string $firstName = null,
        ?string $middleName = null,
        ?string $lastName = null,
        ?string $email = null,
        ?bool $isActive = true,
        ?UserTypeEnum $type = null,
    ): self {
        return new self(
            createdAt: $createdAt ?? new DateTimeImmutable('2024-01-01 00:00:00'),
            firstName: $firstName ?? 'John',
            middleName: $middleName,
            lastName: $lastName ?? 'Doe',
            email: $email ?? 'john.doe@example.com',
            isActive: $isActive ?? true,
            type: $type ?? UserTypeEnum::Admin,
        );
    }
}
