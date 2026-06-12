<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query\Expression;

/**
 * Marks a string as a raw SQL fragment that is used verbatim, without identifier
 * escaping or validation. Never construct it from untrusted input.
 */
final readonly class RawExpression
{
    public function __construct(public string $expression)
    {
    }
}
