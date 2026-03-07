<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Utils;

use MarekSkopal\ORM\Schema\Enum\CaseEnum;
use MarekSkopal\ORM\Utils\CaseUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(CaseUtils::class)]
final class CaseUtilsTest extends TestCase
{
    #[TestWith(['first_name', 'firstName'])]
    #[TestWith(['user_type_enum', 'userTypeEnum'])]
    #[TestWith(['id', 'id'])]
    #[TestWith(['created_at', 'createdAt'])]
    public function testToCamelCase(string $input, string $expected): void
    {
        self::assertSame($expected, CaseUtils::toCamelCase($input));
    }

    #[TestWith(['firstName', 'first_name'])]
    #[TestWith(['UserTypeEnum', 'user_type_enum'])]
    #[TestWith(['id', 'id'])]
    #[TestWith(['createdAt', 'created_at'])]
    public function testToSnakeCase(string $input, string $expected): void
    {
        self::assertSame($expected, CaseUtils::toSnakeCase($input));
    }

    #[TestWith(['first_name', 'firstName'])]
    public function testToCaseWithCamelCase(string $input, string $expected): void
    {
        self::assertSame($expected, CaseUtils::toCase(CaseEnum::CamelCase, $input));
    }

    #[TestWith(['firstName', 'first_name'])]
    public function testToCaseWithSnakeCase(string $input, string $expected): void
    {
        self::assertSame($expected, CaseUtils::toCase(CaseEnum::SnakeCase, $input));
    }
}
