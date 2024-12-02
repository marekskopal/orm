<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests;

use MarekSkopal\ORM\Database\SqliteDatabase;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Repository\RepositoryInterface;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\SchemaFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ORM::class)]
final class ORMTest extends TestCase
{
    public function testGetRepository(): void
    {
        $database = new SqliteDatabase(':memory:');

        $orm = new ORM($database, SchemaFixture::create());

        $repository = $orm->getRepository(UserFixture::class);

        self::assertInstanceOf(RepositoryInterface::class, $repository);
    }
}
