<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Database\AbstractDatabase;
use MarekSkopal\ORM\Database\SqliteDatabase;
use MarekSkopal\ORM\Entity\EntityCache;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Entity\EntityReflection;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Query\SelectFactory;
use MarekSkopal\ORM\Repository\AbstractRepository;
use MarekSkopal\ORM\Schema\Builder\ColumnSchemaFactory;
use MarekSkopal\ORM\Schema\Builder\EntitySchemaFactory;
use MarekSkopal\ORM\Schema\Builder\SchemaBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AddressFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Utils\CaseUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ORM::class)]
#[UsesClass(Column::class)]
#[UsesClass(Entity::class)]
#[UsesClass(ManyToOne::class)]
#[UsesClass(AbstractDatabase::class)]
#[UsesClass(SqliteDatabase::class)]
#[UsesClass(EntityCache::class)]
#[UsesClass(EntityFactory::class)]
#[UsesClass(EntityReflection::class)]
#[UsesClass(Mapper::class)]
#[UsesClass(QueryProvider::class)]
#[UsesClass(Select::class)]
#[UsesClass(SelectFactory::class)]
#[UsesClass(AbstractRepository::class)]
#[UsesClass(ColumnSchemaFactory::class)]
#[UsesClass(EntitySchemaFactory::class)]
#[UsesClass(SchemaBuilder::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(PropertyTypeEnum::class)]
#[UsesClass(Schema::class)]
#[UsesClass(CaseUtils::class)]
final class IntegrationTest extends TestCase
{
    public function testSelectEntity(): void
    {
        $database = new SqliteDatabase(':memory:');
        $sqlFileContent = file_get_contents(__DIR__ . '/Fixtures/Database/database_users.sql');
        if ($sqlFileContent === false) {
            throw new \RuntimeException('Cannot read database.sql file');
        }

        $schema = new SchemaBuilder()
            ->addEntityPath(__DIR__ . '/Fixtures/Entity')
            ->build();

        $orm = new ORM($database, $schema);

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

    public function testSelectEntityRelationManyToOne(): void
    {
        $database = new SqliteDatabase(':memory:');
        $sqlFileContent = file_get_contents(__DIR__ . '/Fixtures/Database/database_users_with_address.sql');
        if ($sqlFileContent === false) {
            throw new \RuntimeException('Cannot read database.sql file');
        }

        $schema = new SchemaBuilder()
            ->addEntityPath(__DIR__ . '/Fixtures/Entity')
            ->build();

        $orm = new ORM($database, $schema);

        foreach (explode(';', $sqlFileContent) as $sql) {
            $sql = trim($sql);
            if ($sql === '') {
                continue;
            }

            $database->getPdo()->exec($sql);
        }

        $repository = $orm->getRepository(UserWithAddressFixture::class);

        $userById = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserWithAddressFixture::class, $userById);
        self::assertEquals(1, $userById->id);

        $address = $userById->address;
        self::assertInstanceOf(AddressFixture::class, $address);
        self::assertEquals(1, $address->id);
    }
}
