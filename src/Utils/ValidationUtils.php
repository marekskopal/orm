<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Utils;

use BackedEnum;
use DateTimeInterface;
use Ramsey\Uuid\UuidInterface;

final class ValidationUtils
{
    public static function checkString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Value is not string');
        }

        return $value;
    }

    public static function checkInt(mixed $value): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException('Value is not integer');
        }

        return $value;
    }

    public static function checkIntString(mixed $value): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException('Value is not integer or string');
        }

        return $value;
    }

    public static function checkFloat(mixed $value): float
    {
        if (!is_float($value)) {
            throw new \InvalidArgumentException('Value is not float');
        }

        return $value;
    }

    public static function checkBool(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new \InvalidArgumentException('Value is not boolean');
        }

        return $value;
    }

    public static function checkUuid(mixed $value): UuidInterface
    {
        if (!($value instanceof UuidInterface)) {
            throw new \InvalidArgumentException('Value is not UUID');
        }

        return $value;
    }

    public static function checkDatetime(mixed $value): DateTimeInterface
    {
        if (!($value instanceof DateTimeInterface)) {
            throw new \InvalidArgumentException('Value is not DateTimeInterface');
        }

        return $value;
    }

    public static function checkEnum(mixed $value): BackedEnum
    {
        if (!($value instanceof BackedEnum)) {
            throw new \InvalidArgumentException('Value is not Enum');
        }

        return $value;
    }

    public static function checkObject(mixed $value): object
    {
        if (!is_object($value)) {
            throw new \InvalidArgumentException('Value is not object');
        }

        return $value;
    }
}
