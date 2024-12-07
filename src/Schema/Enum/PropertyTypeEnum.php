<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Enum;

use BackedEnum;
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
    case Enum;
    case Relation;
    case Extension;

    public static function fromTypeName(string $typeName): self
    {
        return match ($typeName) {
            'string' => self::String,
            'int' => self::Int,
            'float' => self::Float,
            'bool' => self::Bool,
            default => throw new \InvalidArgumentException('Invalid type name'),
        };
    }

    public static function fromClassName(string $className): self
    {
        if (is_a($className, UuidInterface::class, true)) {
            return self::Uuid;
        }

        if (is_a($className, DateTime::class, true)) {
            return self::DateTime;
        }

        if (is_a($className, DateTimeImmutable::class, true)) {
            return self::DateTimeImmutable;
        }

        if (is_a($className, BackedEnum::class, true)) {
            return self::Enum;
        }

        throw new \InvalidArgumentException('Invalid class name');
    }
}
