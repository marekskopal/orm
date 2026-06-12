<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Utils;

use MarekSkopal\ORM\Utils\QuoteUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuoteUtils::class)]
final class QuoteUtilsTest extends TestCase
{
    #[TestWith(['users', '`', '`users`'])]
    #[TestWith(['users', '"', '"users"'])]
    #[TestWith(['na`me', '`', '`na``me`'])]
    #[TestWith(['name` , (SELECT 1) -- ', '`', '`name`` , (SELECT 1) -- `'])]
    #[TestWith(['na"me', '"', '"na""me"'])]
    #[TestWith(['na`me', '"', '"na`me"'])]
    public function testQuote(string $name, string $quoteChar, string $expected): void
    {
        self::assertSame($expected, QuoteUtils::quote($name, $quoteChar));
    }
}
