<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\UnitOfWork;

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
use MarekSkopal\ORM\Exception\QueryException;
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
use MarekSkopal\ORM\Tests\Fixtures\Entity\Code;
use MarekSkopal\ORM\Tests\Fixtures\Entity\PostFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\TagFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithTagsFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UuidChildFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UuidItemFixture;
use MarekSkopal\ORM\Transaction\TransactionProvider;
use MarekSkopal\ORM\UnitOfWork\UnitOfWork;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use MarekSkopal\ORM\Utils\ValidationUtils;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use ReflectionClass;

#[CoversClass(UnitOfWork::class)]
#[UsesClass(NormalizerGenerator::class)]
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
final class UnitOfWorkTest extends TestCase
{
    public function testPersistedEntityIsTheIdentityMappedInstance(): void
    {
        $orm = $this->createOrm('database_users.sql');
        $repository = $orm->getRepository(UserFixture::class);

        $user = UserFixture::create(firstName: 'Bob');
        $repository->persist($user);

        self::assertSame(3, $user->id);
        self::assertSame($user, $repository->findOne(['id' => 3]));
    }

    public function testDeletedEntityIsEvicted(): void
    {
        $orm = $this->createOrm('database_users.sql');
        $repository = $orm->getRepository(UserFixture::class);

        $user = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $user);
        $repository->delete($user);
        self::assertNull($repository->findOne(['id' => 1]));

        // A row that reappears with the same id is a new entity, not the deleted instance.
        $this->pdo($orm)->exec(
            "INSERT INTO users (id, created_at, first_name, last_name, email, is_active, type) VALUES (1, 1704067200, 'New', 'Row', 'n@example.com', 1, 'user')",
        );
        $reappeared = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $reappeared);
        self::assertNotSame($user, $reappeared);
        self::assertSame('New', $reappeared->firstName);
    }

    public function testUnchangedEntityIssuesNoQuery(): void
    {
        $orm = $this->createOrm('database_users.sql');
        $repository = $orm->getRepository(UserFixture::class);
        $user = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $user);

        CountingStatement::reset();
        $repository->persist($user);

        self::assertSame(0, CountingStatement::$count);
    }

    public function testUpdateWritesOnlyChangedColumns(): void
    {
        $orm = $this->createOrm('database_users.sql');
        $repository = $orm->getRepository(UserFixture::class);
        $user = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $user);

        CountingStatement::reset();
        $user->email = 'changed@example.com';
        $repository->persist($user);

        self::assertSame(['UPDATE "users" SET "email"=? WHERE "id"=?'], CountingStatement::$queries);

        // The snapshot follows the write: persisting again writes nothing.
        CountingStatement::reset();
        $repository->persist($user);
        self::assertSame(0, CountingStatement::$count);

        $orm->getIdentityMap()->clear();
        self::assertSame('changed@example.com', $repository->findOne(['id' => 1])?->email);
    }

    public function testManyNewEntitiesInsertWithOneStatement(): void
    {
        $orm = $this->createOrm('database_users.sql');
        $unitOfWork = $orm->getUnitOfWork();

        $users = [];
        for ($i = 0; $i < 2000; $i++) {
            $users[] = $user = UserFixture::create(email: 'user' . $i . '@example.com');
            $unitOfWork->persist($user);
        }

        // Scheduling writes nothing.
        self::assertSame(0, CountingStatement::$count);

        $unitOfWork->flush();

        self::assertSame(1, CountingStatement::$count);
        self::assertSame(3, $users[0]->id);
        self::assertSame(2002, $users[1999]->id);
        self::assertSame($users[1999], $orm->getRepository(UserFixture::class)->findOne(['id' => 2002]));
    }

    public function testInsertsParentsBeforeChildren(): void
    {
        $orm = $this->createOrm('database_cascade.sql');

        $author = new AuthorFixture('Ann', new Collection());
        $posts = [new PostFixture('First', $author), new PostFixture('Second', $author), new PostFixture('Third', $author)];
        foreach ($posts as $post) {
            $author->posts[] = $post;
        }

        CountingStatement::reset();
        $orm->getRepository(AuthorFixture::class)->persist($author);

        // One insert for the author, one for all posts.
        self::assertSame(2, CountingStatement::$count);
        self::assertSame([1, 2, 3], array_map(static fn(PostFixture $post): int => $post->id, $posts));
        self::assertSame(
            [['author_id' => 1]],
            $this->query($orm, 'SELECT DISTINCT author_id FROM posts')->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    public function testInsertsSelfReferencingEntitiesLevelByLevel(): void
    {
        $orm = $this->createOrm('database_categories.sql');
        $this->pdo($orm)->exec('DELETE FROM categories');

        $root = new CategoryFixture('Root', null);
        $child = new CategoryFixture('Child', $root);
        $grandchild = new CategoryFixture('Grandchild', $child);
        $unitOfWork = $orm->getUnitOfWork();
        $unitOfWork->persist($grandchild)->persist($child)->persist($root);
        $unitOfWork->flush();

        self::assertSame(
            [['name' => 'Root', 'parent_id' => null], ['name' => 'Child', 'parent_id' => $root->id], ['name' => 'Grandchild', 'parent_id' => $child->id]],
            $this->query($orm, 'SELECT name, parent_id FROM categories ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    public function testDeletesAreGroupedAndChildrenGoFirst(): void
    {
        $orm = $this->createOrm('database_cascade.sql');
        $this->pdo($orm)->exec("INSERT INTO authors (id, name) VALUES (1, 'Ann')");
        $this->pdo($orm)->exec("INSERT INTO posts (id, title, author_id) VALUES (1, 'a', 1), (2, 'b', 1), (3, 'c', 1)");

        $author = $orm->getRepository(AuthorFixture::class)->findOne(['id' => 1]);
        self::assertInstanceOf(AuthorFixture::class, $author);

        CountingStatement::reset();
        $orm->getRepository(AuthorFixture::class)->delete($author);

        // Load the posts, delete them in one statement, then the author.
        self::assertCount(3, CountingStatement::$queries);
        self::assertStringStartsWith('SELECT', CountingStatement::$queries[0]);
        self::assertSame('DELETE FROM "posts" WHERE "id" IN (?,?,?)', CountingStatement::$queries[1]);
        self::assertSame('DELETE FROM "authors" WHERE "id" IN (?)', CountingStatement::$queries[2]);
    }

    public function testManyToManyJoinRowsAreDiffed(): void
    {
        $orm = $this->createOrm('database_many_to_many.sql');
        $user = $orm->getRepository(UserWithTagsFixture::class)->findOne(['id' => 1]);
        $database = $orm->getRepository(TagFixture::class)->findOne(['id' => 3]);
        self::assertInstanceOf(UserWithTagsFixture::class, $user);
        self::assertInstanceOf(TagFixture::class, $database);

        // php, orm -> orm, database
        unset($user->tags[0]);
        $user->tags[] = $database;

        CountingStatement::reset();
        $orm->getRepository(UserWithTagsFixture::class)->persist($user);

        self::assertSame(
            [
                'SELECT "tag_id" FROM "user_tags" WHERE "user_id" = ?',
                'DELETE FROM "user_tags" WHERE "user_id" = ? AND "tag_id" IN (?)',
                'INSERT INTO "user_tags" ("user_id", "tag_id") VALUES (?, ?)',
            ],
            CountingStatement::$queries,
        );
        self::assertSame(
            [2, 3],
            $this->query($orm, 'SELECT tag_id FROM user_tags WHERE user_id = 1 ORDER BY tag_id')->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    public function testPrimaryKeyThatIsNotAutoIncrementIsInserted(): void
    {
        $orm = $this->createOrm('database_users.sql');
        $this->pdo($orm)->exec('CREATE TABLE codes (id INTEGER PRIMARY KEY, code TEXT NOT NULL)');

        $code = new Code(42, Uuid::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'));
        $orm->getRepository(Code::class)->persist($code);

        self::assertSame(42, $code->id);
        self::assertSame(
            [['id' => 42, 'code' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479']],
            $this->query($orm, 'SELECT id, code FROM codes')->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    public function testUuidKeyedEntitiesRoundTrip(): void
    {
        $orm = $this->createOrm('database_users.sql');
        $this->pdo($orm)->exec('CREATE TABLE uuid_items (id TEXT PRIMARY KEY, name TEXT NOT NULL)');
        $this->pdo($orm)->exec('CREATE TABLE uuid_children (id TEXT PRIMARY KEY, name TEXT NOT NULL, item_id TEXT NOT NULL)');

        $item = new UuidItemFixture(Uuid::uuid4(), 'Item');
        $child = new UuidChildFixture(Uuid::uuid4(), 'Child', $item);
        $orm->getUnitOfWork()->persist($child)->persist($item)->flush();

        $items = $orm->getRepository(UuidItemFixture::class);
        self::assertSame($item, $items->findOne(['id' => $item->id]));

        $orm->getIdentityMap()->clear();
        $loadedChild = $orm->getRepository(UuidChildFixture::class)->findOne(['id' => $child->id]);
        self::assertInstanceOf(UuidChildFixture::class, $loadedChild);
        self::assertTrue($child->id->equals($loadedChild->id));
        // The foreign key is seeded as a UUID, and the proxy is the instance the identity map returns.
        self::assertTrue($item->id->equals($loadedChild->item->id));
        self::assertTrue(new ReflectionClass(UuidItemFixture::class)->isUninitializedLazyObject($loadedChild->item));
        self::assertSame($loadedChild->item, $items->findOne(['id' => $item->id->toString()]));
        self::assertSame('Item', $loadedChild->item->name);

        CountingStatement::reset();
        $loadedChild->name = 'Renamed';
        $orm->getRepository(UuidChildFixture::class)->persist($loadedChild);
        self::assertSame(['UPDATE "uuid_children" SET "name"=? WHERE "id"=?'], CountingStatement::$queries);
    }

    public function testDetachedEntityWithIdIsUpdatedInFull(): void
    {
        $orm = $this->createOrm('database_users.sql');

        $user = UserFixture::create(firstName: 'Detached');
        $user->id = 2;
        CountingStatement::reset();
        $orm->getRepository(UserFixture::class)->persist($user);

        self::assertCount(1, CountingStatement::$queries);
        self::assertStringStartsWith('UPDATE "users" SET "created_at"=?,"first_name"=?', CountingStatement::$queries[0]);
        self::assertSame('Detached', $this->query($orm, 'SELECT first_name FROM users WHERE id = 2')->fetchColumn());
    }

    public function testFailedFlushRollsBackAndKeepsWorkScheduled(): void
    {
        $orm = $this->createOrm('database_cascade.sql');
        $unitOfWork = $orm->getUnitOfWork();
        $unitOfWork->persist(new AuthorFixture('Ann', new Collection()));
        // users is not a table in this database, so the second insert fails.
        $unitOfWork->persist(UserFixture::create());

        try {
            $unitOfWork->flush();
            self::fail('flush() did not fail');
        } catch (\RuntimeException) {
            // Expected: the users table does not exist.
        }

        self::assertSame(0, (int) $this->query($orm, 'SELECT COUNT(*) FROM authors')->fetchColumn());
        self::assertFalse($this->pdo($orm)->inTransaction());
    }

    public function testFailedFlushUndoesItsEffectsSoARetryWritesEverything(): void
    {
        $orm = $this->createOrm('database_cascade.sql');
        $author = new AuthorFixture('Ann', new Collection());
        $post = new PostFixture('First', $author);
        $author->posts[] = $post;

        // The authors insert succeeds, the posts insert fails.
        $this->pdo($orm)->exec('DROP TABLE posts');
        $unitOfWork = $orm->getUnitOfWork();
        $unitOfWork->persist($author);

        try {
            $unitOfWork->flush();
            self::fail('flush() did not fail');
        } catch (QueryException) {
            // Expected: the posts table does not exist.
        }

        // The rolled-back insert left no id, registration or snapshot behind.
        self::assertFalse(isset($author->id));
        self::assertFalse($unitOfWork->isManaged($author));

        $this->pdo($orm)->exec(
            'CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, author_id INTEGER NOT NULL)',
        );
        $unitOfWork->flush();

        self::assertSame(1, $author->id);
        self::assertSame(
            [['title' => 'First', 'author_id' => 1]],
            $this->query($orm, 'SELECT title, author_id FROM posts')->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    public function testFailedFlushRestoresSnapshotsOfUpdatedEntities(): void
    {
        $orm = $this->createOrm('database_users.sql');
        $user = $orm->getRepository(UserFixture::class)->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $user);

        // Deletes run after updates, so the update has run when the delete fails.
        $ghost = new AuthorFixture('No table', new Collection());
        $ghost->id = 5;
        $user->firstName = 'Changed';
        $unitOfWork = $orm->getUnitOfWork();
        $unitOfWork->persist($user)->remove($ghost);

        try {
            $unitOfWork->flush();
            self::fail('flush() did not fail');
        } catch (QueryException) {
            // Expected: the authors table does not exist.
        }

        // The update was rolled back; its snapshot too, so the change is still detected.
        $unitOfWork->clear();
        CountingStatement::reset();
        $orm->getRepository(UserFixture::class)->persist($user);
        self::assertSame(['UPDATE "users" SET "first_name"=? WHERE "id"=?'], CountingStatement::$queries);
    }

    public function testRefreshOfDetachedEntityKeepsNoSnapshot(): void
    {
        $orm = $this->createOrm('database_users.sql');
        $user = $orm->getRepository(UserFixture::class)->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $user);

        $orm->getIdentityMap()->clear();
        $orm->getUnitOfWork()->refresh($user);

        self::assertSame('John', $user->firstName);
        self::assertFalse($orm->getIdentityMap()->hasSnapshot($user));
    }

    public function testDetachedEntityWithManualKeyIsUpdatedNotInserted(): void
    {
        $orm = $this->createOrm('database_users.sql');
        $this->pdo($orm)->exec('CREATE TABLE codes (id INTEGER PRIMARY KEY, code TEXT NOT NULL)');
        $this->pdo($orm)->exec("INSERT INTO codes VALUES (1, 'f47ac10b-58cc-4372-a567-0e02b2c3d479')");
        $repository = $orm->getRepository(Code::class);

        $loaded = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(Code::class, $loaded);
        // A per-request clear detaches the entity.
        $orm->getIdentityMap()->clear();
        $loaded->code = Uuid::fromString('00000000-0000-4000-8000-000000000001');
        $new = new Code(2, Uuid::fromString('00000000-0000-4000-8000-000000000002'));

        CountingStatement::reset();
        $orm->getUnitOfWork()->persist($loaded)->persist($new)->flush();

        // One query finds which keys exist; the existing row is updated, the new one inserted.
        self::assertSame(
            [
                'SELECT "c"."id" FROM "codes" "c" WHERE "c"."id" IN (?,?)',
                'INSERT INTO "codes" ("id","code") VALUES (?,?)',
                'UPDATE "codes" SET "code"=? WHERE "id"=?',
            ],
            CountingStatement::$queries,
        );
        self::assertSame(
            [[1, '00000000-0000-4000-8000-000000000001'], [2, '00000000-0000-4000-8000-000000000002']],
            $this->query($orm, 'SELECT id, code FROM codes ORDER BY id')->fetchAll(PDO::FETCH_NUM),
        );
        self::assertSame($loaded, $repository->findOne(['id' => 1]));
    }

    public function testRemovingProxiesDoesNotLoadThem(): void
    {
        $orm = $this->createOrm('database_users_with_address.sql');
        $users = $orm->getRepository(UserWithAddressFixture::class)->select()->orderBy('id')->fetchAll();
        $first = $users[0]->address;
        $second = $users[1]->address;
        // One of the rows is already gone; deleting it must still be harmless.
        $this->pdo($orm)->exec('DELETE FROM addresses WHERE id = 2');

        CountingStatement::reset();
        $orm->getUnitOfWork()->remove($first)->remove($second)->flush();

        self::assertSame(['DELETE FROM "addresses" WHERE "id" IN (?,?)'], CountingStatement::$queries);
        self::assertTrue(new ReflectionClass(AddressWithUsersFixture::class)->isUninitializedLazyObject($first));
        self::assertSame(0, (int) $this->query($orm, 'SELECT COUNT(*) FROM addresses')->fetchColumn());
    }

    public function testFlushInsideCallerTransactionDoesNotCommit(): void
    {
        $orm = $this->createOrm('database_users.sql');
        $pdo = $this->pdo($orm);

        $pdo->beginTransaction();
        $orm->getRepository(UserFixture::class)->persist(UserFixture::create());
        self::assertTrue($pdo->inTransaction());
        $pdo->rollBack();

        self::assertSame(2, (int) $this->query($orm, 'SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testRefreshDiscardsUnflushedChanges(): void
    {
        $orm = $this->createOrm('database_users.sql');
        $repository = $orm->getRepository(UserFixture::class);
        $user = $repository->findOne(['id' => 1]);
        self::assertInstanceOf(UserFixture::class, $user);

        $user->firstName = 'Changed';
        $orm->getUnitOfWork()->refresh($user);
        self::assertSame('John', $user->firstName);

        // After a refresh the entity is unchanged, so persisting writes nothing.
        CountingStatement::reset();
        $repository->persist($user);
        self::assertSame(0, CountingStatement::$count);
    }

    public function testRemoveCancelsPersistAndViceVersa(): void
    {
        $orm = $this->createOrm('database_users.sql');
        $unitOfWork = $orm->getUnitOfWork();
        $user = UserFixture::create();

        $unitOfWork->persist($user)->remove($user);
        self::assertFalse($unitOfWork->isManaged($user));
        $unitOfWork->clear();
        $unitOfWork->flush();

        self::assertSame(0, CountingStatement::$count);
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

    private function query(ORM $orm, string $sql): PDOStatement
    {
        $statement = $this->pdo($orm)->query($sql);
        self::assertNotFalse($statement);

        return $statement;
    }

    private function pdo(ORM $orm): PDO
    {
        return $orm->getQueryProvider()->getDatabase()->getPdo();
    }
}
