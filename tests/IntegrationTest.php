<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests;

use MarekSkopal\ORM\Database\SqliteDatabase;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\SchemaFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ORM::class)]
final class IntegrationTest extends TestCase
{
    public function testSelectEntity(): void
    {
        $database = new SqliteDatabase(':memory:');
        $sqlFileContent = file_get_contents(__DIR__ . '/Fixtures/Database/database.sql');
        if ($sqlFileContent === false) {
            throw new \RuntimeException('Cannot read database.sql file');
        }

        $orm = new ORM($database, SchemaFixture::create());

        foreach (explode(';', $sqlFileContent) as $sql) {
            $sql = trim($sql);
            if ($sql === '') {
                continue;
            }

            $database->getPdo()->exec($sql);
        }

        $repository = $orm->getRepository(UserFixture::class);

        $userById = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $userById);
        self::assertEquals(1, $userById->id);

        $userByFirstName = $repository->findOne(['first_name' => 'Jane']);
        self::assertInstanceOf(UserFixture::class, $userByFirstName);
        self::assertEquals(2, $userByFirstName->id);

        $userNotFound = $repository->findOne(['id' => 3]);
        self::assertNull($userNotFound);

        $users = iterator_to_array($repository->find());
        self::assertCount(2, $users);
    }
}
