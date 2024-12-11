<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Utils;

use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
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

    #[TestWith(['relationClass' => UserFixture::class, 'expected' => 'userFixtureId'])]
    public function testGetRelationColumnName(string $relationClass, string $expected): void
    {
        self::assertSame($expected, NameUtils::getRelationColumnName($relationClass));
    }

    #[TestWith(['name' => 'address', 'expected' => '`address`'])]
    public function testEscape(string $name, string $expected): void
    {
        self::assertSame($expected, NameUtils::escape($name));
    }
}
