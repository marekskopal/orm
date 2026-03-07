<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Utils;

use DateTimeImmutable;
use InvalidArgumentException;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Utils\ValidationUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use stdClass;

#[CoversClass(ValidationUtils::class)]
final class ValidationUtilsTest extends TestCase
{
    public function testCheckStringReturnsString(): void
    {
        self::assertSame('hello', ValidationUtils::checkString('hello'));
    }

    public function testCheckStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationUtils::checkString(42);
    }

    public function testCheckIntReturnsInt(): void
    {
        self::assertSame(7, ValidationUtils::checkInt(7));
    }

    public function testCheckIntThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationUtils::checkInt('7');
    }

    public function testCheckIntStringReturnsInt(): void
    {
        self::assertSame(7, ValidationUtils::checkIntString(7));
    }

    public function testCheckIntStringReturnsString(): void
    {
        self::assertSame('abc', ValidationUtils::checkIntString('abc'));
    }

    public function testCheckIntStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationUtils::checkIntString(1.5);
    }

    public function testCheckFloatReturnsFloat(): void
    {
        self::assertSame(3.14, ValidationUtils::checkFloat(3.14));
    }

    public function testCheckFloatThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationUtils::checkFloat(3);
    }

    public function testCheckBoolReturnsBool(): void
    {
        self::assertTrue(ValidationUtils::checkBool(true));
        self::assertFalse(ValidationUtils::checkBool(false));
    }

    public function testCheckBoolThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationUtils::checkBool(1);
    }

    public function testCheckUuidReturnsUuid(): void
    {
        $uuid = Uuid::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');
        self::assertSame($uuid, ValidationUtils::checkUuid($uuid));
    }

    public function testCheckUuidThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationUtils::checkUuid('f47ac10b-58cc-4372-a567-0e02b2c3d479');
    }

    public function testCheckDatetimeReturnsDatetime(): void
    {
        $dt = new DateTimeImmutable('2024-01-01');
        self::assertSame($dt, ValidationUtils::checkDatetime($dt));
    }

    public function testCheckDatetimeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationUtils::checkDatetime('2024-01-01');
    }

    public function testCheckEnumReturnsEnum(): void
    {
        self::assertSame(UserTypeEnum::Admin, ValidationUtils::checkEnum(UserTypeEnum::Admin));
    }

    public function testCheckEnumThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationUtils::checkEnum('admin');
    }

    public function testCheckObjectReturnsObject(): void
    {
        $obj = new stdClass();
        self::assertSame($obj, ValidationUtils::checkObject($obj));
    }

    public function testCheckObjectThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationUtils::checkObject('not-object');
    }
}
