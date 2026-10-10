<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Relation;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ForeignKey;
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
use MarekSkopal\ORM\Query\Expression\RawExpression;
use MarekSkopal\ORM\Query\Factory\DeleteFactory;
use MarekSkopal\ORM\Query\Factory\InsertFactory;
use MarekSkopal\ORM\Query\Factory\SelectFactory;
use MarekSkopal\ORM\Query\Factory\UpdateFactory;
use MarekSkopal\ORM\Query\Insert;
use MarekSkopal\ORM\Query\Model\Join;
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
use MarekSkopal\ORM\Schema\Compiler\NormalizerGenerator;
use MarekSkopal\ORM\Schema\Compiler\SchemaCompiler;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Tests\Fixtures\Database\CountingStatement;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AddressWithUsersFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AuthorFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\CategoryFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\CitizenFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\PassportFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\PostFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\ProfileFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\TagFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithTagsFixture;
use MarekSkopal\ORM\Transaction\TransactionProvider;
use MarekSkopal\ORM\UnitOfWork\UnitOfWork;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use MarekSkopal\ORM\Utils\QuoteUtils;
use MarekSkopal\ORM\Utils\ValidationUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(RelationResolver::class)]
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
#[UsesClass(ForeignKey::class)]
#[UsesClass(ORM::class)]
#[UsesClass(RawExpression::class)]
#[UsesClass(Join::class)]
#[UsesClass(NormalizerGenerator::class)]
#[UsesClass(UnitOfWork::class)]
#[UsesClass(QuoteUtils::class)]
final class RelationResolverTest extends TestCase
{
    public function testManyToOneProxiesInitialiseInOneBatch(): void
    {
        $orm = $this->createOrm('database_users_with_address.sql');
        $users = $orm->getRepository(UserWithAddressFixture::class)->select()->orderBy('id')->fetchAll();

        CountingStatement::$count = 0;
        // Reading the seeded primary key does not initialise the proxy.
        self::assertSame(1, $users[0]->address->id);
        self::assertSame(0, CountingStatement::$count);
        self::assertTrue($this->isUninitialised($users[1]->address));

        self::assertSame('Springfield', $users[0]->address->city);
        self::assertSame('Shelbyville', $users[1]->address->city);
        // Both pending addresses load with one query.
        self::assertSame(1, CountingStatement::$count);
    }

    public function testManyToOneProxyIsSharedAndCanonical(): void
    {
        $orm = $this->createOrm('database_cascade.sql');
        $pdo = $orm->getQueryProvider()->getDatabase()->getPdo();
        $pdo->exec("INSERT INTO authors (id, name) VALUES (1, 'Ann')");
        $pdo->exec("INSERT INTO posts (id, title, author_id) VALUES (1, 'First', 1), (2, 'Second', 1)");

        $posts = $orm->getRepository(PostFixture::class)->findAll();
        self::assertSame($posts[0]->author, $posts[1]->author);

        // The proxy is the identity-mapped instance for its id.
        self::assertSame($posts[0]->author, $orm->getRepository(AuthorFixture::class)->findOne(['id' => 1]));
        self::assertSame('Ann', $posts[0]->author->name);
    }

    public function testRowFromLaterSelectInitialisesProxyWithoutQuery(): void
    {
        $orm = $this->createOrm('database_users_with_address.sql');
        $users = $orm->getRepository(UserWithAddressFixture::class)->select()->orderBy('id')->fetchAll();

        $addresses = $orm->getRepository(AddressWithUsersFixture::class)->findAll();
        self::assertContains($users[0]->address, $addresses);

        CountingStatement::$count = 0;
        self::assertSame('Springfield', $users[0]->address->city);
        self::assertSame(0, CountingStatement::$count);
    }

    public function testSelfReferencingRelationResolvesToLoadedEntities(): void
    {
        $orm = $this->createOrm('database_categories.sql');
        $categories = $orm->getRepository(CategoryFixture::class)->select()->orderBy('id')->fetchAll();

        CountingStatement::$count = 0;
        self::assertSame($categories[0], $categories[1]->parent);
        self::assertSame($categories[1], $categories[2]->parent);
        self::assertNull($categories[0]->parent);
        self::assertSame('Books', $categories[2]->parent->name);
        self::assertSame(0, CountingStatement::$count);
    }

    public function testMissingRelatedEntityThrowsOnAccess(): void
    {
        $orm = $this->createOrm('database_users_with_address.sql');
        $orm->getQueryProvider()->getDatabase()->getPdo()->exec('DELETE FROM addresses WHERE id = 2');
        $users = $orm->getRepository(UserWithAddressFixture::class)->select()->orderBy('id')->fetchAll();

        self::assertSame('Springfield', $users[0]->address->city);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Entity "' . AddressWithUsersFixture::class . '" with id "2" not found');
        self::assertSame('', $users[1]->address->city);
    }

    public function testLazyCollectionsLoadWithOneQuery(): void
    {
        $orm = $this->createOrm('database_users_with_address.sql');
        $address = $orm->getRepository(AddressWithUsersFixture::class)->findOne(['id' => 1]);
        self::assertInstanceOf(AddressWithUsersFixture::class, $address);
        self::assertFalse($address->users->isInitialized());

        CountingStatement::$count = 0;
        self::assertSame(
            ['John'],
            array_map(static fn(UserWithAddressFixture $user): string => $user->firstName, $address->users->toArray()),
        );
        self::assertSame(1, CountingStatement::$count);
    }

    public function testLazyManyToManyLoadsWithOneJoinQuery(): void
    {
        $orm = $this->createOrm('database_many_to_many.sql');
        $user = $orm->getRepository(UserWithTagsFixture::class)->findOne(['id' => 1]);
        $tag = $orm->getRepository(TagFixture::class)->findOne(['id' => 2]);
        self::assertInstanceOf(UserWithTagsFixture::class, $user);
        self::assertInstanceOf(TagFixture::class, $tag);

        CountingStatement::$count = 0;
        self::assertSame(['php', 'orm'], $this->names($user->tags->toArray()));
        self::assertSame(1, CountingStatement::$count);

        self::assertSame(['John', 'Jane'], $this->names($tag->users->toArray()));
        self::assertSame(2, CountingStatement::$count);
    }

    public function testWithPreloadsManyToOne(): void
    {
        $orm = $this->createOrm('database_users_with_address.sql');

        CountingStatement::$count = 0;
        $users = $orm->getRepository(UserWithAddressFixture::class)->select()->with('address')->orderBy('id')->fetchAll();
        self::assertSame(['Springfield', 'Shelbyville'], array_map(
            static fn(UserWithAddressFixture $user): string => $user->address->city,
            $users,
        ));
        self::assertFalse($this->isUninitialised($users[0]->address));
        self::assertSame(2, CountingStatement::$count);
    }

    public function testWithPreloadsOneToMany(): void
    {
        $orm = $this->createOrm('database_users_with_address.sql');

        CountingStatement::$count = 0;
        $addresses = $orm->getRepository(AddressWithUsersFixture::class)->select()->with('users')->orderBy('id')->fetchAll();
        self::assertTrue($addresses[0]->users->isInitialized());
        self::assertSame(
            ['John'],
            array_map(static fn(UserWithAddressFixture $user): string => $user->firstName, $addresses[0]->users->toArray()),
        );
        self::assertSame(
            ['Jane'],
            array_map(static fn(UserWithAddressFixture $user): string => $user->firstName, $addresses[1]->users->toArray()),
        );
        self::assertSame(2, CountingStatement::$count);
    }

    public function testWithPreloadsManyToManyAndNestedPaths(): void
    {
        $orm = $this->createOrm('database_many_to_many.sql');

        CountingStatement::$count = 0;
        $users = $orm->getRepository(UserWithTagsFixture::class)->select()->with('tags')->orderBy('id')->fetchAll();
        self::assertSame(['php', 'orm'], $this->names($users[0]->tags->toArray()));
        self::assertSame(['orm', 'database'], $this->names($users[1]->tags->toArray()));
        // The shared tag is one instance.
        self::assertSame($users[0]->tags[1], $users[1]->tags[0]);
        self::assertSame(2, CountingStatement::$count);

        $orm->getIdentityMap()->clear();
        CountingStatement::$count = 0;
        $tags = $orm->getRepository(TagFixture::class)->select()->with('users.tags')->orderBy('id')->fetchAll();
        foreach ($tags as $tag) {
            foreach ($tag->users as $user) {
                self::assertCount(2, $user->tags);
            }
        }
        self::assertSame(['John', 'Jane'], $this->names($tags[1]->users->toArray()));
        // Tags, their users, and the users' tags: one query per level.
        self::assertSame(3, CountingStatement::$count);
    }

    public function testWithPreloadsInverseOneToOne(): void
    {
        $orm = $this->createOrm('database_one_to_one.sql');

        CountingStatement::$count = 0;
        $profiles = $orm->getRepository(ProfileFixture::class)->select()->with('user')->orderBy('id')->fetchAll();
        self::assertSame('John', $profiles[0]->user?->name);
        self::assertSame('Jane', $profiles[1]->user?->name);
        self::assertSame(2, CountingStatement::$count);

        $orm->getQueryProvider()->getDatabase()->getPdo()->exec('INSERT INTO profiles (id, bio) VALUES (3, \'Nobody\')');
        $orm->getIdentityMap()->clear();
        $orphan = $orm->getRepository(ProfileFixture::class)->select()->with('user')->where(['id' => 3])->fetchOne();
        self::assertInstanceOf(ProfileFixture::class, $orphan);
        self::assertNull($orphan->user);
    }

    public function testNullableInverseOneToOneLoadsWithItsOwner(): void
    {
        $orm = $this->createOrm('database_one_to_one.sql');
        $orm->getQueryProvider()->getDatabase()->getPdo()->exec("INSERT INTO profiles (id, bio) VALUES (3, 'Nobody')");

        CountingStatement::reset();
        $profiles = $orm->getRepository(ProfileFixture::class)->select()->orderBy('id')->fetchAll();
        // The profiles, then all their users with one query: a proxy cannot become null.
        self::assertSame(2, CountingStatement::$count);
        self::assertSame('Jane', $profiles[1]->user?->name);
        self::assertNull($profiles[2]->user);
        self::assertSame(2, CountingStatement::$count);

        $orm->getIdentityMap()->clear();
        $orphan = $orm->getRepository(ProfileFixture::class)->findOne(['id' => 3]);
        self::assertInstanceOf(ProfileFixture::class, $orphan);
        self::assertNull($orphan->user);
    }

    public function testNonNullableInverseOneToOneIsALazyProxy(): void
    {
        $orm = $this->createOrm('database_passports.sql');

        CountingStatement::reset();
        $citizens = $orm->getRepository(CitizenFixture::class)->select()->orderBy('id')->fetchAll();
        // Only the citizens: a non-nullable inverse relation is a proxy, not a preload.
        self::assertSame(1, CountingStatement::$count);
        self::assertTrue($this->isUninitialised($citizens[0]->passport));

        self::assertSame('P-002', $citizens[1]->passport->number);
        self::assertSame(2, CountingStatement::$count);
        self::assertSame($citizens[1], $citizens[1]->passport->citizen);

        // The citizen without a passport fails on first access, not at hydration.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OneToOne inverse entity "' . PassportFixture::class . '" not found for FK value "3"');
        self::assertSame('', $citizens[2]->passport->number);
    }

    public function testNonNullableInverseOneToOneProxyResolvesToTheLoadedEntity(): void
    {
        $orm = $this->createOrm('database_passports.sql');
        $passport = $orm->getRepository(PassportFixture::class)->findOne(['id' => 1]);
        self::assertInstanceOf(PassportFixture::class, $passport);

        CountingStatement::reset();
        $citizen = $passport->citizen;
        self::assertSame('John', $citizen->name);
        // Initialising the proxy queries the passport by its FK; the row maps to the instance already loaded.
        self::assertSame('P-001', $citizen->passport->number);
        self::assertSame($passport->id, $citizen->passport->id);
        self::assertSame(2, CountingStatement::$count);
    }

    public function testWithRejectsMissingNonNullableInverseOneToOne(): void
    {
        $orm = $this->createOrm('database_passports.sql');
        $repository = $orm->getRepository(CitizenFixture::class);

        CountingStatement::reset();
        $citizens = $repository->select()->with('passport')->where(['id', 'IN', [1, 2]])->orderBy('id')->fetchAll();
        self::assertSame(
            ['P-001', 'P-002'],
            array_map(static fn(CitizenFixture $citizen): string => $citizen->passport->number, $citizens),
        );
        self::assertSame(2, CountingStatement::$count);

        // Preloaded as missing, so a non-nullable relation fails at hydration.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found for FK value "3"');
        $repository->select()->with('passport')->where(['id' => 3])->fetchOne();
    }

    public function testWithFillsLazyCollectionsOfLoadedOwnersAndLeavesNothingBehind(): void
    {
        $orm = $this->createOrm('database_users_with_address.sql');
        $repository = $orm->getRepository(AddressWithUsersFixture::class);
        $addresses = $repository->select()->orderBy('id')->fetchAll();
        self::assertFalse($addresses[0]->users->isInitialized());

        // The owners exist already: their lazy collections are filled in place.
        $again = $repository->select()->with('users')->orderBy('id')->fetchAll();
        self::assertSame($addresses, $again);
        self::assertTrue($addresses[0]->users->isInitialized());

        CountingStatement::reset();
        self::assertCount(1, $addresses[1]->users);
        self::assertSame(0, CountingStatement::$count);

        // No preloaded entry outlives the call: a refresh reads the current rows.
        $orm->getQueryProvider()->getDatabase()->getPdo()->exec('UPDATE users SET address_id = 1 WHERE id = 2');
        $orm->getUnitOfWork()->refresh($addresses[0]);
        self::assertCount(2, $addresses[0]->users);
    }

    public function testWithSkipsOwningRelationsAlreadyLoaded(): void
    {
        $orm = $this->createOrm('database_users_with_address.sql');
        $orm->getRepository(AddressWithUsersFixture::class)->findAll();

        CountingStatement::reset();
        $users = $orm->getRepository(UserWithAddressFixture::class)->select()->with('address')->fetchAll();

        // Only the users: every address is loaded already.
        self::assertSame(1, CountingStatement::$count);
        self::assertSame('Springfield', $users[0]->address->city);
    }

    public function testWithRejectsUnknownAndNonRelationProperties(): void
    {
        $orm = $this->createOrm('database_users_with_address.sql');
        $repository = $orm->getRepository(UserWithAddressFixture::class);

        try {
            $repository->select()->with('firstName')->fetchAll();
            self::fail('with() accepted a non-relation property');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('"firstName"', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"unknown" is not a property');
        $repository->select()->with('address.unknown')->fetchAll();
    }

    public function testProxiesStillInitialiseAfterCacheClear(): void
    {
        $orm = $this->createOrm('database_users_with_address.sql');
        $users = $orm->getRepository(UserWithAddressFixture::class)->select()->orderBy('id')->fetchAll();

        $orm->getIdentityMap()->clear();

        CountingStatement::$count = 0;
        self::assertSame('Shelbyville', $users[1]->address->city);
        self::assertSame(1, CountingStatement::$count);
    }

    private function createOrm(string $sqlFile): ORM
    {
        $database = new SqliteDatabase(':memory:');
        $sql = file_get_contents(__DIR__ . '/../Fixtures/Database/' . $sqlFile);
        self::assertIsString($sql);
        foreach (array_filter(
            array_map('trim', explode(';', $sql)),
            static fn(string $statement): bool => $statement !== '',
        ) as $statement) {
            $database->getPdo()->exec($statement);
        }

        CountingStatement::attach($database->getPdo());

        return new ORM($database, new SchemaBuilder()->addEntityPath(__DIR__ . '/../Fixtures/Entity')->build());
    }

    private function isUninitialised(object $object): bool
    {
        return new ReflectionClass($object)->isUninitializedLazyObject($object);
    }

    /**
     * @param array<object> $entities
     * @return list<string>
     */
    private function names(array $entities): array
    {
        // @phpstan-ignore-next-line property.notFound
        return array_values(array_map(static fn(object $entity): string => $entity->name, $entities));
    }
}
