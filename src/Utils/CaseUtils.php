<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Utils;

use MarekSkopal\ORM\Schema\Enum\CaseEnum;

class CaseUtils
{
    public static function toCase(CaseEnum $case, string $string): string
    {
        return match ($case) {
            CaseEnum::CamelCase => self::toCamelCase($string),
            CaseEnum::SnakeCase => self::toSnakeCase($string),
        };
    }

    public static function toCamelCase(string $string): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $string))));
    }

    public static function toSnakeCase(string $string): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }
}
