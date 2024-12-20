<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Utils;

use ReflectionClass;

final class NameUtils
{
    public static function getTableName(string $name): string
    {
        $lastChar = substr($name, -1);
        $secondLastChar = substr($name, -2, 1);
        if ($lastChar === 'y' && !in_array($secondLastChar, ['a', 'e', 'i', 'o', 'u'], true)) {
            return substr($name, 0, -1) . 'ies';
        }

        if ($lastChar === 's') {
            return $name . 'es';
        }

        return $name . 's';
    }

    /** @param ReflectionClass<object>|class-string<object> $reflectionClass */
    public static function getRelationColumnName(ReflectionClass|string $reflectionClass): string
    {
        if (is_string($reflectionClass)) {
            $reflectionClass = new ReflectionClass($reflectionClass);
        }

        return lcfirst($reflectionClass->getShortName()) . 'Id';
    }

    public static function escape(string $name): string
    {
        return '`' . $name . '`';
    }
}
