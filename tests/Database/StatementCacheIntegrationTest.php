<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Database;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToMany;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Attribute\OneToOne;
use MarekSkopal\ORM\Database\AbstractDatabase;
use MarekSkopal\ORM\Database\SqliteDatabase;
use MarekSkopal\ORM\Entity\IdentityMap;
use MarekSkopal\ORM\Exception\TransactionException;
use MarekSkopal\ORM\Mapper\Collection;
use MarekSkopal\ORM\Mapper\ExtensionMapperProvider;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Query\Delete;
use MarekSkopal\ORM\Query\Factory\DeleteFactory;
use MarekSkopal\ORM\Query\Factory\InsertFactory;
use MarekSkopal\ORM\Query\Factory\SelectFactory;
use MarekSkopal\ORM\Query\Factory\UpdateFactory;
use MarekSkopal\ORM\Query\Insert;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Query\Update;
use MarekSkopal\ORM\Query\Where\WhereBuilder;
use MarekSkopal\ORM\Relation\RelationResolver;
use MarekSkopal\ORM\Repository\AbstractRepository;
use MarekSkopal\ORM\Schema\Builder\ClassScanner\ClassScanner;
use MarekSkopal\ORM\Schema\Builder\ColumnSchemaFactory;
use MarekSkopal\ORM\Schema\Builder\EntitySchemaFactory;
use MarekSkopal\ORM\Schema\Builder\SchemaBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Compiler\CodeExporter;
use MarekSkopal\ORM\Schema\Compiler\ExtractorGenerator;
use MarekSkopal\ORM\Schema\Compiler\HydratorGenerator;
use MarekSkopal\ORM\Schema\Compiler\SchemaCompiler;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Tests\Fixtures\Database\CountingStatement;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AddressWithUsersFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Transaction\TransactionProvider;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use MarekSkopal\ORM\Utils\ValidationUtils;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractDatabase::class)]
#[UsesClass(RelationResolver::class)]
#[UsesClass(TransactionProvider::class)]
#[UsesClass(TransactionException::class)]
#[UsesClass(Column::class)]
#[UsesClass(ColumnEnum::class)]
#[UsesClass(Entity::class)]
#[UsesClass(ManyToMany::class)]
#[UsesClass(ManyToOne::class)]
#[UsesClass(OneToOne::class)]
#[UsesClass(SqliteDatabase::class)]
#[UsesClass(IdentityMap::class)]
#[UsesClass(QueryProvider::class)]
#[UsesClass(Select::class)]
#[UsesClass(SelectFactory::class)]
#[UsesClass(InsertFactory::class)]
#[UsesClass(Insert::class)]
#[UsesClass(UpdateFactory::class)]
#[UsesClass(Update::class)]
#[UsesClass(DeleteFactory::class)]
#[UsesClass(Delete::class)]
#[UsesClass(AbstractRepository::class)]
#[UsesClass(ColumnSchemaFactory::class)]
#[UsesClass(EntitySchemaFactory::class)]
#[UsesClass(SchemaBuilder::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(PropertyTypeEnum::class)]
#[UsesClass(Schema::class)]
#[UsesClass(SchemaProvider::class)]
#[UsesClass(CaseUtils::class)]
#[UsesClass(ClassScanner::class)]
#[UsesClass(NameUtils::class)]
#[UsesClass(ValidationUtils::class)]
#[UsesClass(OneToMany::class)]
#[UsesClass(WhereBuilder::class)]
#[UsesClass(Collection::class)]
#[UsesClass(SchemaCompiler::class)]
#[UsesClass(HydratorGenerator::class)]
#[UsesClass(ExtractorGenerator::class)]
#[UsesClass(CodeExporter::class)]
#[UsesClass(ExtensionMapperProvider::class)]
final class StatementCacheIntegrationTest extends TestCase
{
    public function testOrmDoesNotConnectUntilItQueries(): void
    {
        $database = new SqliteDatabase(':memory:');
        $orm = new ORM($database, $this->buildSchema());
        $repository = $orm->getRepository(UserWithAddressFixture::class);
        $select = $repository->select()->where(['id' => 1]);

        self::assertFalse($database->isConnected());
        self::assertStringStartsWith('SELECT', $select->getSql());
        self::assertFalse($database->isConnected());
    }

    public function testRepeatedLazyLoadsPrepareOnce(): void
    {
        $orm = $this->createOrm();
        $pdo = $orm->getQueryProvider()->getDatabase()->getPdo();
        $pdo->exec('DELETE FROM users');
        $pdo->exec('DELETE FROM addresses');
        for ($i = 1; $i <= 200; $i++) {
            $pdo->exec(sprintf("INSERT INTO addresses (id, street, city, country) VALUES (%d, 'Street', 'City', 'Country')", $i));
            $pdo->exec(sprintf(
                "INSERT INTO users (id, created_at, first_name, last_name, email, is_active, type, address_id) VALUES (%d, 1704067200, 'U', 'L', 'e', 1, 'user', %d)",
                $i,
                $i,
            ));
        }

        CountingStatement::reset();
        $addresses = $orm->getRepository(AddressWithUsersFixture::class)->findAll();
        foreach ($addresses as $address) {
            self::assertCount(1, $address->users);
        }

        // 1 query for the addresses and 200 lazy collection loads of one SQL shape: 2 prepares.
        self::assertSame(201, CountingStatement::$count);
        self::assertSame(2, CountingStatement::$prepares);
    }

    public function testNestedStreamingOfTheSameQueryKeepsBothCursors(): void
    {
        $orm = $this->createOrm();
        $repository = $orm->getRepository(UserWithAddressFixture::class);

        $pairs = [];
        foreach ($repository->select()->orderBy('id')->iterate() as $outer) {
            foreach ($repository->select()->orderBy('id')->iterate() as $inner) {
                $pairs[] = $outer->id . '-' . $inner->id;
            }

            // A cached statement for the same SQL must not reset the outer cursor.
            self::assertCount(2, $repository->select()->orderBy('id')->fetchAll());
        }

        self::assertSame(['1-1', '1-2', '2-1', '2-2'], $pairs);
    }

    public function testReadsAndWritesDoNotKeepTheDatabaseLocked(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'orm-lock-');
        self::assertIsString($file);

        try {
            $orm = $this->createOrm($file);
            $repository = $orm->getRepository(UserWithAddressFixture::class);
            $other = new PDO('sqlite:' . $file);
            $other->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $other->setAttribute(PDO::ATTR_TIMEOUT, 0);
            $assertWritable = static fn(): int|false => $other->exec('UPDATE users SET last_name = last_name WHERE id = 2');

            $user = $repository->findOne(['id' => 1]);
            self::assertNotNull($user);
            self::assertSame(1, $assertWritable());

            self::assertNotNull($repository->select()->where(['id' => 1])->fetchAssocOne());
            self::assertSame(1, $assertWritable());

            self::assertSame(2, $repository->select()->count());
            self::assertSame(1, $assertWritable());

            // A generator abandoned after its first row.
            $repository->select()->orderBy('id')->iterate()->current();
            self::assertSame(1, $assertWritable());

            // A generator abandoned after its first row.
            $repository->select()->orderBy('id')->iterateAssoc()->current();
            self::assertSame(1, $assertWritable());

            $user->lastName = 'Changed';
            $repository->persist($user);
            self::assertSame(1, $assertWritable());

            $copy = new UserWithAddressFixture(
                createdAt: $user->createdAt,
                firstName: 'Copy',
                middleName: null,
                lastName: 'Copy',
                email: 'copy@example.com',
                isActive: true,
                type: $user->type,
                address: $user->address,
                secondAddress: null,
            );
            $repository->persist($copy);
            self::assertSame(1, $assertWritable());

            $repository->delete($copy);
            self::assertSame(1, $assertWritable());
        } finally {
            unset($orm, $repository, $user, $copy, $other);
            gc_collect_cycles();
            @unlink($file);
        }
    }

    private function createOrm(string $file = ':memory:'): ORM
    {
        $database = new SqliteDatabase($file);
        $sql = file_get_contents(__DIR__ . '/../Fixtures/Database/database_users_with_address.sql');
        self::assertIsString($sql);
        foreach (array_filter(
            array_map('trim', explode(';', $sql)),
            static fn(string $statement): bool => $statement !== '',
        ) as $statement) {
            $database->getPdo()->exec($statement);
        }

        CountingStatement::attach($database->getPdo());

        return new ORM($database, $this->buildSchema());
    }

    private function buildSchema(): Schema
    {
        return new SchemaBuilder()->addEntityPath(__DIR__ . '/../Fixtures/Entity')->build();
    }
}
