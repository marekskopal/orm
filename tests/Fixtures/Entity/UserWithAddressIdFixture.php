<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ForeignKey;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;

/** Scalar foreign key columns: the properties hold the raw keys, not the related entities. */
#[Entity(table: 'users')]
final class UserWithAddressIdFixture
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
        #[Column(type: Type::Int)]
        #[ForeignKey(entityClass: AddressWithUsersFixture::class)]
        public int $addressId,
        #[Column(type: Type::Int, nullable: true)]
        #[ForeignKey(entityClass: AddressWithUsersFixture::class, nullable: true)]
        public ?int $secondAddressId,
    ) {
    }
}
