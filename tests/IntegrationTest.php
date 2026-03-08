<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToMany;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Attribute\OneToOne;
use MarekSkopal\ORM\Database\AbstractDatabase;
use MarekSkopal\ORM\Database\SqliteDatabase;
use MarekSkopal\ORM\Entity\EntityCache;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Entity\EntityReflection;
use MarekSkopal\ORM\Exception\TransactionException;
use MarekSkopal\ORM\Mapper\Collection;
use MarekSkopal\ORM\Mapper\Mapper;
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
use MarekSkopal\ORM\Repository\AbstractRepository;
use MarekSkopal\ORM\Schema\Builder\ClassScanner\ClassScanner;
use MarekSkopal\ORM\Schema\Builder\ColumnSchemaFactory;
use MarekSkopal\ORM\Schema\Builder\EntitySchemaFactory;
use MarekSkopal\ORM\Schema\Builder\SchemaBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AddressWithUsersFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AuthorFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\PostFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\ProfileFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\TagFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithProfileFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithTagsFixture;
use MarekSkopal\ORM\Transaction\TransactionProvider;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use MarekSkopal\ORM\Utils\ValidationUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ORM::class)]
#[UsesClass(TransactionProvider::class)]
#[UsesClass(TransactionException::class)]
#[UsesClass(Column::class)]
#[UsesClass(ColumnEnum::class)]
#[UsesClass(Entity::class)]
#[UsesClass(ManyToMany::class)]
#[UsesClass(ManyToOne::class)]
#[UsesClass(OneToOne::class)]
#[UsesClass(AbstractDatabase::class)]
#[UsesClass(SqliteDatabase::class)]
#[UsesClass(EntityCache::class)]
#[UsesClass(EntityFactory::class)]
#[UsesClass(EntityReflection::class)]
#[UsesClass(Mapper::class)]
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

        $users = iterator_to_array($repository->findAll());
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
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(AddressWithUsersFixture::class, $address);
        self::assertEquals(1, $address->id);
    }

    public function testSelectEntityRelationOneToMany(): void
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

        $repository = $orm->getRepository(AddressWithUsersFixture::class);

        $address = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(AddressWithUsersFixture::class, $address);
        self::assertInstanceOf(Collection::class, $address->users);
        self::assertEquals(1, count($address->users));
        self::assertInstanceOf(UserWithAddressFixture::class, $address->users[0]);
        self::assertEquals(1, $address->users[0]->id);
    }

    public function testInsertEntity(): void
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

        $user = UserFixture::create();

        $repository->persist($user);

        self::assertSame(3, $user->id);

        $users = $repository->findAll();
        self::assertCount(3, iterator_to_array($users));
    }

    public function testDeleteEntity(): void
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

        $user = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $user);
        $repository->delete($user);

        $users = iterator_to_array($repository->findAll());
        self::assertCount(1, $users);
        self::assertSame(2, $users[0]->id);
    }

    public function testTransactionCommit(): void
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

        $orm->getTransactionProvider()->transaction(function () use ($repository): void {
            $repository->persist(UserFixture::create(firstName: 'Alice'));
            $repository->persist(UserFixture::create(firstName: 'Bob'));
        });

        $users = iterator_to_array($repository->findAll());
        self::assertCount(4, $users);
    }

    public function testTransactionRollback(): void
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

        try {
            $orm->getTransactionProvider()->transaction(function () use ($repository): void {
                $repository->persist(UserFixture::create(firstName: 'Alice'));

                throw new \RuntimeException('Something went wrong');
            });
        } catch (\RuntimeException) {
        }

        $users = iterator_to_array($repository->findAll());
        self::assertCount(2, $users);
    }

    public function testTransactionNestedThrows(): void
    {
        $database = new SqliteDatabase(':memory:');

        $schema = new SchemaBuilder()
            ->addEntityPath(__DIR__ . '/Fixtures/Entity')
            ->build();

        $orm = new ORM($database, $schema);

        $this->expectException(TransactionException::class);

        $orm->getTransactionProvider()->transaction(function () use ($orm): void {
            $orm->getTransactionProvider()->transaction(function (): void {
            });
        });
    }

    public function testUpdateEntity(): void
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

        $user = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $user);
        $user->firstName = 'Jane';
        $repository->persist($user);

        $user = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $user);
        self::assertSame('Jane', $user->firstName);
    }

    public function testSelectEntityRelationOneToOne(): void
    {
        $database = new SqliteDatabase(':memory:');
        $sqlFileContent = file_get_contents(__DIR__ . '/Fixtures/Database/database_one_to_one.sql');
        if ($sqlFileContent === false) {
            throw new \RuntimeException('Cannot read database_one_to_one.sql file');
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

        $repository = $orm->getRepository(UserWithProfileFixture::class);

        $user = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserWithProfileFixture::class, $user);
        self::assertSame('John', $user->name);

        $profile = $user->profile;
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(ProfileFixture::class, $profile);
        self::assertSame(1, $profile->id);
        self::assertSame('Hello, I am John', $profile->bio);
    }

    public function testSelectEntityRelationOneToOneInverse(): void
    {
        $database = new SqliteDatabase(':memory:');
        $sqlFileContent = file_get_contents(__DIR__ . '/Fixtures/Database/database_one_to_one.sql');
        if ($sqlFileContent === false) {
            throw new \RuntimeException('Cannot read database_one_to_one.sql file');
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

        $repository = $orm->getRepository(ProfileFixture::class);

        $profile = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(ProfileFixture::class, $profile);
        self::assertSame('Hello, I am John', $profile->bio);

        $user = $profile->user;
        self::assertInstanceOf(UserWithProfileFixture::class, $user);
        self::assertSame(1, $user->id);
        self::assertSame('John', $user->name);
    }

    public function testSelectEntityRelationManyToMany(): void
    {
        $database = new SqliteDatabase(':memory:');
        $sqlFileContent = file_get_contents(__DIR__ . '/Fixtures/Database/database_many_to_many.sql');
        if ($sqlFileContent === false) {
            throw new \RuntimeException('Cannot read database_many_to_many.sql file');
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

        $repository = $orm->getRepository(UserWithTagsFixture::class);

        $user = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserWithTagsFixture::class, $user);
        self::assertSame('John', $user->name);

        $tags = $user->tags;
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(Collection::class, $tags);
        self::assertCount(2, $tags);
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(TagFixture::class, $tags[0]);
        self::assertSame('php', $tags[0]->name);
        self::assertSame('orm', $tags[1]->name);
    }

    public function testSelectEntityRelationManyToManyInverse(): void
    {
        $database = new SqliteDatabase(':memory:');
        $sqlFileContent = file_get_contents(__DIR__ . '/Fixtures/Database/database_many_to_many.sql');
        if ($sqlFileContent === false) {
            throw new \RuntimeException('Cannot read database_many_to_many.sql file');
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

        $repository = $orm->getRepository(TagFixture::class);

        $tag = $repository->findOne(['id' => 2]);
        self::assertInstanceOf(TagFixture::class, $tag);
        self::assertSame('orm', $tag->name);

        $users = $tag->users;
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(Collection::class, $users);
        self::assertCount(2, $users);
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(UserWithTagsFixture::class, $users[0]);
        self::assertSame('John', $users[0]->name);
        self::assertSame('Jane', $users[1]->name);
    }

    private function createCascadeOrm(): ORM
    {
        $database = new SqliteDatabase(':memory:');
        $sqlFileContent = file_get_contents(__DIR__ . '/Fixtures/Database/database_cascade.sql');
        if ($sqlFileContent === false) {
            throw new \RuntimeException('Cannot read database_cascade.sql file');
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

        return $orm;
    }

    public function testCascadePersistOneToMany(): void
    {
        $orm = $this->createCascadeOrm();
        $authorRepository = $orm->getRepository(AuthorFixture::class);
        $postRepository = $orm->getRepository(PostFixture::class);

        $post1 = new PostFixture('First Post', new AuthorFixture('', new Collection()));
        $post2 = new PostFixture('Second Post', new AuthorFixture('', new Collection()));
        $author = new AuthorFixture('John', new Collection([$post1, $post2]));
        $post1->author = $author;
        $post2->author = $author;

        $authorRepository->persist($author);

        self::assertSame(1, $author->id);
        self::assertSame(1, $post1->id);
        self::assertSame(2, $post2->id);

        $posts = iterator_to_array($postRepository->findAll());
        self::assertCount(2, $posts);
    }

    public function testCascadeRemoveOneToMany(): void
    {
        $orm = $this->createCascadeOrm();
        $authorRepository = $orm->getRepository(AuthorFixture::class);
        $postRepository = $orm->getRepository(PostFixture::class);

        $post1 = new PostFixture('First Post', new AuthorFixture('', new Collection()));
        $post2 = new PostFixture('Second Post', new AuthorFixture('', new Collection()));
        $author = new AuthorFixture('John', new Collection([$post1, $post2]));
        $post1->author = $author;
        $post2->author = $author;

        $authorRepository->persist($author);

        // Reload to get initialized collection
        $orm->getEntityCache()->clear();
        $author = $authorRepository->findOne(['id' => 1]);
        self::assertInstanceOf(AuthorFixture::class, $author);

        $authorRepository->delete($author);

        self::assertCount(0, iterator_to_array($authorRepository->findAll()));
        self::assertCount(0, iterator_to_array($postRepository->findAll()));
    }
}
