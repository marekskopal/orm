<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Schema\Builder\ClassScanner;

use MarekSkopal\ORM\Schema\Builder\ClassScanner\ClassScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassScanner::class)]
final class ClassScannerTest extends TestCase
{
    public function testFindClasses(): void
    {
        $classScanner = new ClassScanner(__DIR__ . '/../../../Fixtures/Entity/UserWithAddressFixture.php');
        $classes = $classScanner->findClasses();

        self::assertCount(1, $classes);
        self::assertSame('MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture', $classes[0]);
    }
}
