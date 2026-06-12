<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Utils;

final class QuoteUtils
{
    /**
     * Quotes an SQL identifier, doubling any embedded quote characters so the
     * identifier cannot break out of the quoted context.
     */
    public static function quote(string $name, string $quoteChar): string
    {
        return $quoteChar . str_replace($quoteChar, $quoteChar . $quoteChar, $name) . $quoteChar;
    }
}
