<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests;

use DateTimeImmutable;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Query\Where\WhereBuilder;
use MarekSkopal\ORM\Schema\Builder\SchemaBuilder;
use MarekSkopal\ORM\Tests\Fixtures\Database\CountingStatement;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AddressWithUsersFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\TagFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithTagsFixture;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests that run against a real database server. Each driver subclass supplies the
 * connection and the dialect of its SQL fixtures; every test here runs on every driver.
 *
 * When the server is unreachable the tests are skipped, so a local run without the databases
 * still passes. CI sets ORM_TEST_REQUIRE_DATABASES=1 to turn that skip into a failure, so a
 * broken database service cannot silently hide the driver tests.
 */
abstract class AbstractDriverIntegrationTestCase extends TestCase
{
    /** Creates the database; its connection opens lazily. */
    abstract protected function connect(): DatabaseInterface;

    /** Suffix of the driver-specific SQL fixture files, e.g. "mysql" for database_users_mysql.sql. */
    abstract protected function getFixtureSuffix(): string;

    protected static function env(string $name, string $default): string
    {
        $value = getenv($name);
        return $value !== false && $value !== '' ? $value : $default;
    }

    private function createOrm(string $fixture): ORM
    {
        try {
            $database = $this->connect();
            // The connection is lazy, so open it here to turn an unreachable server into a skip.
            $database->connect();
        } catch (PDOException $e) {
            $message = static::class . ': database not available: ' . $e->getMessage();
            if (self::env('ORM_TEST_REQUIRE_DATABASES', '0') === '1') {
                self::fail($message);
            }

            self::markTestSkipped($message);
        }

        $sqlFile = __DIR__ . '/Fixtures/Database/' . $fixture . '_' . $this->getFixtureSuffix() . '.sql';
        $sqlFileContent = file_get_contents($sqlFile);
        if ($sqlFileContent === false) {
            throw new \RuntimeException('Cannot read SQL file: ' . $sqlFile);
        }

        foreach (explode(';', $sqlFileContent) as $sql) {
            $sql = trim($sql);
            if ($sql === '') {
                continue;
            }

            $database->getPdo()->exec($sql);
        }

        $schema = new SchemaBuilder()
            ->addEntityPath(__DIR__ . '/Fixtures/Entity')
            ->build();

        return new ORM($database, $schema);
    }

    public function testSelectEntity(): void
    {
        $repository = $this->createOrm('database_users')->getRepository(UserFixture::class);

        $userById = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $userById);
        self::assertSame(1, $userById->id);
        // is_active is BOOLEAN on PostgreSQL (pdo_pgsql returns bool) and TINYINT(1) on MySQL (int).
        self::assertTrue($userById->isActive);
        self::assertEquals(new DateTimeImmutable('2024-01-01 00:00:00'), $userById->createdAt);

        $userByFirstName = $repository->findOne(['first_name' => 'Jane']);
        self::assertInstanceOf(UserFixture::class, $userByFirstName);
        self::assertSame(2, $userByFirstName->id);
        self::assertFalse($userByFirstName->isActive);

        self::assertNull($repository->findOne(['id' => 3]));

        self::assertCount(2, $repository->findAll());

        $inactiveUsers = $repository->findAll(['is_active' => false]);
        self::assertCount(1, $inactiveUsers);
        self::assertSame(2, $inactiveUsers[0]->id);
    }

    public function testWhereConditions(): void
    {
        $repository = $this->createOrm('database_users')->getRepository(UserFixture::class);

        // Booleans bind as 0/1, which both a native BOOLEAN and a TINYINT(1) column accept.
        self::assertSame([1], $this->ids($repository->findAll(['isActive' => true])));
        self::assertSame([2], $this->ids($repository->findAll(['isActive', 'IN', [false]])));

        // Empty IN / NOT IN must not render "IN ()", a syntax error on MySQL and PostgreSQL.
        self::assertSame([], $repository->findAll(['id', 'IN', []]));
        self::assertSame([1, 2], $this->ids($repository->findAll(['id', 'NOT IN', []])));

        self::assertSame([1], $this->ids($repository->findAll(['middleName' => null])));
        self::assertSame([2], $this->ids($repository->findAll(['middleName', '!=', null])));

        self::assertSame([1, 2], $this->ids($repository->select()->where(['id' => 1])->orWhere(['firstName' => 'Jane'])->fetchAll()));
        self::assertSame([1], $this->ids(
            $repository->select()->where(['isActive' => true])->where(static function (WhereBuilder $where): void {
                $where->where(['firstName' => 'John'])->orWhere(['firstName' => 'Jane']);
            })->fetchAll(),
        ));
        self::assertSame([2], $this->ids($repository->findAll(['firstName', 'LIKE', 'Ja%'])));

        $select = $repository->select()->orderBy('id', 'DESC');
        self::assertSame([2, 1], $this->ids($select->fetchAll()));
        self::assertSame([2], $this->ids($repository->select()->orderBy('id')->limit(1)->offset(1)->fetchAll()));

        // count() drops LIMIT / OFFSET and leaves the builder usable.
        self::assertSame(2, $repository->select()->limit(1)->offset(5)->count());
        self::assertSame(2, $select->count());
        self::assertSame([2, 1], $this->ids($select->fetchAll()));
    }

    public function testSelectEntityRelationManyToOne(): void
    {
        $repository = $this->createOrm('database_users_with_address')->getRepository(UserWithAddressFixture::class);

        $userById = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserWithAddressFixture::class, $userById);
        self::assertSame(1, $userById->id);
        self::assertSame(1, $userById->address->id);
        self::assertSame('Springfield', $userById->address->city);
        self::assertNull($userById->secondAddress);
    }

    public function testRelationPathsAndEagerLoading(): void
    {
        $orm = $this->createOrm('database_users_with_address');
        $repository = $orm->getRepository(UserWithAddressFixture::class);

        self::assertSame([2], $this->ids($repository->findAll(['address.city' => 'Shelbyville'])));
        self::assertSame([1], $this->ids($repository->findAll(['address' => 1])));
        self::assertSame(2, $repository->select()->where(['address.country' => 'USA'])->count());

        $orm->getIdentityMap()->clear();
        $users = $repository->select()->with('address')->orderBy('id')->fetchAll();
        self::assertSame(['Springfield', 'Shelbyville'], array_map(
            static fn(UserWithAddressFixture $user): string => $user->address->city,
            $users,
        ));
        self::assertSame(
            $orm->getIdentityMap()->get(AddressWithUsersFixture::class, 1),
            $users[0]->address,
        );
    }

    public function testManyToOneProxiesAreSharedAndBatchLoaded(): void
    {
        $orm = $this->createOrm('database_users_with_address');
        $users = $orm->getRepository(UserWithAddressFixture::class)->select()->orderBy('id')->fetchAll();
        CountingStatement::attach($orm->getQueryProvider()->getDatabase()->getPdo());

        // Reading the seeded id does not load the address.
        self::assertSame(2, $users[1]->address->id);
        self::assertSame(0, CountingStatement::$count);

        self::assertSame(['Springfield', 'Shelbyville'], array_map(
            static fn(UserWithAddressFixture $user): string => $user->address->city,
            $users,
        ));
        // Every pending address loads with one WHERE id IN (...) query.
        self::assertSame(1, CountingStatement::$count);

        // The proxy is the identity-mapped instance for its id.
        self::assertSame($users[0]->address, $orm->getRepository(AddressWithUsersFixture::class)->findOne(['id' => 1]));
    }

    public function testWithPreloadsEachRelationLevelWithOneQuery(): void
    {
        $orm = $this->createOrm('database_users_with_address');
        CountingStatement::attach($orm->getQueryProvider()->getDatabase()->getPdo());

        $addresses = $orm->getRepository(AddressWithUsersFixture::class)->select()->with('users.address')->orderBy('id')->fetchAll();
        self::assertSame(['John'], array_map(
            static fn(UserWithAddressFixture $user): string => $user->firstName,
            $addresses[0]->users->toArray(),
        ));
        self::assertSame($addresses[1], $addresses[1]->users[0]->address);
        // Addresses, their users, and the users' addresses.
        self::assertSame(3, CountingStatement::$count);
    }

    public function testManyToManyLoadsThroughJoinTable(): void
    {
        $orm = $this->createOrm('database_many_to_many');
        CountingStatement::attach($orm->getQueryProvider()->getDatabase()->getPdo());

        $user = $orm->getRepository(UserWithTagsFixture::class)->findOne(['id' => 1]);
        self::assertInstanceOf(UserWithTagsFixture::class, $user);
        $tagNames = array_map(static fn(TagFixture $tag): string => $tag->name, $user->tags->toArray());
        sort($tagNames);
        self::assertSame(['orm', 'php'], $tagNames);
        // The user, then the tags with one query joining the join table.
        self::assertSame(2, CountingStatement::$count);

        $orm->getIdentityMap()->clear();
        CountingStatement::$count = 0;
        $tags = $orm->getRepository(TagFixture::class)->select()->with('users')->orderBy('id')->fetchAll();
        self::assertSame([1, 2, 1], array_map(static fn(TagFixture $tag): int => count($tag->users), $tags));
        self::assertSame(2, CountingStatement::$count);
    }

    public function testFlushBatchesInsertsAndUpdatesOnlyChangedColumns(): void
    {
        $orm = $this->createOrm('database_users');
        $pdo = $orm->getQueryProvider()->getDatabase()->getPdo();
        CountingStatement::attach($pdo);

        $users = [];
        for ($i = 0; $i < 50; $i++) {
            $users[] = $user = UserFixture::create(email: 'batch' . $i . '@example.com');
            $orm->getUnitOfWork()->persist($user);
        }

        $orm->getUnitOfWork()->flush();

        // One multi-row INSERT; ids continue the sequence in row order.
        self::assertSame(1, CountingStatement::$count);
        self::assertSame(range(3, 52), array_map(static fn(UserFixture $user): int => $user->id, $users));
        self::assertSame($users[10], $orm->getRepository(UserFixture::class)->findOne(['id' => 13]));
        $statement = $pdo->query('SELECT email FROM users WHERE id = 13');
        self::assertNotFalse($statement);
        self::assertSame('batch10@example.com', $statement->fetchColumn());

        CountingStatement::reset();
        $users[10]->firstName = 'Changed';
        $orm->getRepository(UserFixture::class)->persist($users[10]);
        self::assertCount(1, CountingStatement::$queries);
        self::assertMatchesRegularExpression('/^UPDATE .users. SET .first_name.=\\? WHERE .id.=\\?$/', CountingStatement::$queries[0]);
    }

    public function testManyToManyJoinRowsAreDiffed(): void
    {
        $orm = $this->createOrm('database_many_to_many');
        $user = $orm->getRepository(UserWithTagsFixture::class)->findOne(['id' => 1]);
        $tag = $orm->getRepository(TagFixture::class)->findOne(['id' => 3]);
        self::assertInstanceOf(UserWithTagsFixture::class, $user);
        self::assertInstanceOf(TagFixture::class, $tag);

        foreach ($user->tags as $key => $existingTag) {
            if ($existingTag->name === 'php') {
                unset($user->tags[$key]);
            }
        }
        $user->tags[] = $tag;
        $orm->getRepository(UserWithTagsFixture::class)->persist($user);

        $statement = $orm->getQueryProvider()->getDatabase()->getPdo()->query(
            'SELECT tag_id FROM user_tags WHERE user_id = 1 ORDER BY tag_id',
        );
        self::assertNotFalse($statement);
        /** @var list<int|string> $tagIds pdo_mysql and pdo_pgsql may return integers or numeric strings */
        $tagIds = $statement->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([2, 3], array_map(static fn(int|string $id): int => (int) $id, $tagIds));
    }

    public function testSelectEntityRelationOneToMany(): void
    {
        $repository = $this->createOrm('database_users_with_address')->getRepository(AddressWithUsersFixture::class);

        $address = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(AddressWithUsersFixture::class, $address);
        self::assertCount(1, $address->users);
        self::assertSame(1, $address->users[0]->id);
    }

    public function testInsertEntity(): void
    {
        $repository = $this->createOrm('database_users')->getRepository(UserFixture::class);

        $user = UserFixture::create(firstName: 'Bob', isActive: false);
        $repository->persist($user);

        self::assertSame(3, $user->id);
        self::assertCount(3, $repository->findAll());

        $repository->persist(UserFixture::create(firstName: 'Alice'));
        self::assertSame([3], $this->ids($repository->findAll(['firstName' => 'Bob'])));
        self::assertSame([4], $this->ids($repository->findAll(['firstName' => 'Alice'])));
    }

    public function testDeleteEntity(): void
    {
        $repository = $this->createOrm('database_users')->getRepository(UserFixture::class);

        $user = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $user);
        $repository->delete($user);

        $users = $repository->findAll();
        self::assertCount(1, $users);
        self::assertSame(2, $users[0]->id);
    }

    public function testUpdateEntity(): void
    {
        $orm = $this->createOrm('database_users');
        $repository = $orm->getRepository(UserFixture::class);

        $user = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $user);
        $user->firstName = 'Jane';
        $user->isActive = false;
        $repository->persist($user);

        $orm->getIdentityMap()->clear();
        $user = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $user);
        self::assertSame('Jane', $user->firstName);
        self::assertFalse($user->isActive);
    }

    /**
     * @param list<UserFixture|UserWithAddressFixture> $users
     * @return list<int>
     */
    private function ids(array $users): array
    {
        return array_map(static fn(UserFixture|UserWithAddressFixture $user): int => $user->id, $users);
    }
}
