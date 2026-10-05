<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity;

use DateTime;
use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserLevelEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Extension\MapperExtension;
use Ramsey\Uuid\UuidInterface;

/** Covers every column type, an extension mapper, and private / readonly properties for generated code. */
#[Entity(table: 'column_types')]
final class TypesFixture
{
    #[Column(type: Type::Int, primary: true, autoIncrement: true)]
    public int $id;

    #[Column(type: Type::String, nullable: true)]
    public ?string $note = null;

    public function __construct(
        #[Column(type: Type::String)]
        public string $name,
        #[Column(type: Type::Int)]
        public int $count,
        #[Column(type: Type::Float)]
        public float $ratio,
        #[Column(type: Type::Boolean)]
        public bool $enabled,
        #[Column(type: Type::Uuid)]
        public UuidInterface $uuid,
        #[Column(type: Type::Timestamp)]
        public DateTime $createdAt,
        #[Column(type: Type::Date)]
        public DateTimeImmutable $day,
        #[ColumnEnum(enum: UserTypeEnum::class)]
        public UserTypeEnum $type,
        #[ColumnEnum(enum: UserLevelEnum::class, nullable: true)]
        public ?UserLevelEnum $level,
        #[Column(type: Type::Decimal, extension: MapperExtension::class)]
        public float $price,
        #[Column(type: Type::String)]
        private readonly string $secret,
    ) {
    }

    public function getSecret(): string
    {
        return $this->secret;
    }
}
