<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Repository\UserRepositoryWithAddressFixture;

#[Entity(table: 'users', repositoryClass: UserRepositoryWithAddressFixture::class)]
class UserWithAddressFixture
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
        #[ColumnEnum(enum: UserTypeEnum::class)]
        public UserTypeEnum $type,
        #[ManyToOne(entityClass: AddressFixture::class)]
        public AddressFixture $address,
        #[ManyToOne(entityClass: AddressFixture::class, nullable: true)]
        public ?AddressFixture $secondAddress,
    ) {
    }
}
