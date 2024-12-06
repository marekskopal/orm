<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Utils;

final class NameUtils
{
    public static function getTableName(string $name): string
    {
        $lastChar = substr($name, -1);
        if ($lastChar === 'y') {
            return substr($name, 0, -1) . 'ies';
        }

        if ($lastChar === 's') {
            return $name . 'es';
        }

        return $name . 's';
    }
}
