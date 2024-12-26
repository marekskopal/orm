<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Repository\UserRepositoryWithAddressFixture;

#[Entity(table: 'users', repositoryClass: UserRepositoryWithAddressFixture::class)]
class UserWithAddressFixture
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
        #[ManyToOne(entityClass: AddressWithUsersFixture::class)]
        public AddressWithUsersFixture $address,
        #[ManyToOne(entityClass: AddressWithUsersFixture::class, nullable: true)]
        public ?AddressWithUsersFixture $secondAddress,
    ) {
    }
}
