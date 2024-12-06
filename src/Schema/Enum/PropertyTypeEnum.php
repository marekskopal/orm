<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Enum;

use DateTime;
use DateTimeImmutable;
use Ramsey\Uuid\UuidInterface;

enum PropertyTypeEnum
{
    case String;
    case Int;
    case Float;
    case Bool;
    case Uuid;
    case DateTime;
    case DateTimeImmutable;
    case Relation;

    public static function fromTypeName(string $typeName): self
    {
        return match ($typeName) {
            'string' => self::String,
            'int' => self::Int,
            'float' => self::Float,
            'bool' => self::Bool,
            UuidInterface::class => self::Uuid,
            DateTime::class => self::DateTime,
            DateTimeImmutable::class => self::DateTimeImmutable,
            default => throw new \InvalidArgumentException('Invalid type name'),
        };
    }
}
