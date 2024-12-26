<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Repository\UserRepositoryFixture;

#[Entity(table: 'users', repositoryClass: UserRepositoryFixture::class)]
final class UserFixture
{
    #[Column(type: 'int', primary: true, autoIncrement: true)]
    public int $id;

    public function __construct(
        #[Column(type: Type::Timestamp)]
        public DateTimeImmutable $createdAt,
        #[Column(type: Type::String)]
        public string $firstName,
        #[Column(type: Type::String, nullable: true)]
        public ?string $middleName,
        #[Column(type: Type::String)]
        public string $lastName,
        #[Column(type: Type::String)]
        public string $email,
        #[Column(type: Type::Boolean)]
        public bool $isActive,
        #[ColumnEnum(enum: UserTypeEnum::class)]
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
