<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Compiler;

use UnitEnum;

/** Renders values and class names as PHP source for generated code. */
final class CodeExporter
{
    public static function value(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value), is_string($value) => var_export($value, true),
            $value instanceof UnitEnum => self::className($value::class) . '::' . $value->name,
            is_array($value) => self::array($value),
            default => throw new \InvalidArgumentException(sprintf('Cannot export value of type "%s".', get_debug_type($value))),
        };
    }

    /** @param class-string $className */
    public static function className(string $className): string
    {
        return '\\' . ltrim($className, '\\');
    }

    /** @param array<mixed> $value */
    private static function array(array $value): string
    {
        if ($value === []) {
            return '[]';
        }

        $items = [];
        foreach ($value as $key => $item) {
            $items[] = array_is_list($value) ? self::value($item) : self::value($key) . ' => ' . self::value($item);
        }

        return '[' . implode(', ', $items) . ']';
    }
}
