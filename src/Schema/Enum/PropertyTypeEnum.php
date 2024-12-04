<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Enum;

use Ramsey\Uuid\UuidInterface;

enum PropertyTypeEnum
{
    case String;
    case Int;
    case Float;
    case Bool;
    case Uuid;
    case Relation;

    public static function fromTypeName(string $typeName): self
    {
        return match ($typeName) {
            'string' => self::String,
            'int' => self::Int,
            'float' => self::Float,
            'bool' => self::Bool,
            UuidInterface::class => self::Uuid,
            default => throw new \InvalidArgumentException('Invalid type name'),
        };
    }
}
