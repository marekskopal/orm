<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Utils;

use MarekSkopal\ORM\Utils\NameUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(NameUtils::class)]
class NameUtilsTest extends TestCase
{
    #[TestWith(['name' => 'user', 'expected' => 'users'])]
    #[TestWith(['name' => 'city', 'expected' => 'cities'])]
    #[TestWith(['name' => 'address', 'expected' => 'addresses'])]
    public function testGetTableName(string $name, string $expected): void
    {
        self::assertSame($expected, NameUtils::getTableName($name));
    }
}
