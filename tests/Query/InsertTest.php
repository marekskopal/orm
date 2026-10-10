<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ForeignKey;
use MarekSkopal\ORM\Attribute\ManyToMany;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Attribute\OneToOne;
use MarekSkopal\ORM\Database\AbstractDatabase;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Database\SqliteDatabase;
use MarekSkopal\ORM\Mapper\ExtensionMapperProvider;
use MarekSkopal\ORM\Query\Insert;
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
use MarekSkopal\ORM\Tests\Fixtures\Entity\Code;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\EntitySchemaFixture;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use MarekSkopal\ORM\Utils\QuoteUtils;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(Insert::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(NameUtils::class)]
#[UsesClass(QuoteUtils::class)]
#[UsesClass(AbstractDatabase::class)]
#[UsesClass(SqliteDatabase::class)]
#[UsesClass(SchemaProvider::class)]
#[UsesClass(Schema::class)]
#[UsesClass(SchemaCompiler::class)]
#[UsesClass(ExtractorGenerator::class)]
#[UsesClass(HydratorGenerator::class)]
#[UsesClass(CodeExporter::class)]
#[UsesClass(Column::class)]
#[UsesClass(ColumnEnum::class)]
#[UsesClass(Entity::class)]
#[UsesClass(ForeignKey::class)]
#[UsesClass(ManyToMany::class)]
#[UsesClass(ManyToOne::class)]
#[UsesClass(OneToMany::class)]
#[UsesClass(OneToOne::class)]
#[UsesClass(ExtensionMapperProvider::class)]
#[UsesClass(ClassScanner::class)]
#[UsesClass(ColumnSchemaFactory::class)]
#[UsesClass(EntitySchemaFactory::class)]
#[UsesClass(SchemaBuilder::class)]
#[UsesClass(NormalizerGenerator::class)]
#[UsesClass(PropertyTypeEnum::class)]
#[UsesClass(CaseUtils::class)]
final class InsertTest extends TestCase
{
    public function testGetSql(): void
    {
        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($this::createStub(PDO::class));
        $database->method('getIdentifierQuoteChar')->willReturn('`');
        $entitySchema = EntitySchemaFixture::create();
        $insert = new Insert($database, UserFixture::class, $entitySchema, $this->createSchemaProvider());
        $insert->entity(UserFixture::create());
        $insert->entity(UserFixture::create());

        self::assertSame(
            'INSERT INTO `users` (`created_at`,`first_name`,`middle_name`,`last_name`,`email`,`is_active`,`type`) VALUES (?,?,?,?,?,?,?),(?,?,?,?,?,?,?)',
            $insert->getSql(),
        );
    }

    public function testGetSqlWithReturning(): void
    {
        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($this::createStub(PDO::class));
        $database->method('getIdentifierQuoteChar')->willReturn('"');
        $database->method('getInsertReturningClause')->willReturn('RETURNING "id"');
        $entitySchema = EntitySchemaFixture::create();
        $insert = new Insert($database, UserFixture::class, $entitySchema, $this->createSchemaProvider());
        $insert->entity(UserFixture::create());

        self::assertSame(
            'INSERT INTO "users" ("created_at","first_name","middle_name","last_name","email","is_active","type") VALUES (?,?,?,?,?,?,?) RETURNING "id"',
            $insert->getSql(),
        );
    }

    public function testGetSqlNoEntities(): void
    {
        $this->expectException(\LogicException::class);

        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($this::createStub(PDO::class));
        $database->method('getIdentifierQuoteChar')->willReturn('`');
        $entitySchema = EntitySchemaFixture::create();
        $insert = new Insert($database, UserFixture::class, $entitySchema, $this->createSchemaProvider());

        $insert->getSql();
    }

    public function testExecuteNoEntities(): void
    {
        $this->expectException(\LogicException::class);

        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($this::createStub(PDO::class));
        $database->method('getIdentifierQuoteChar')->willReturn('`');
        $entitySchema = EntitySchemaFixture::create();
        $insert = new Insert($database, UserFixture::class, $entitySchema, $this->createSchemaProvider());

        $insert->execute();
    }

    public function testExecuteWithReturningAssignsIds(): void
    {
        $database = $this->createSqliteDatabase();
        $insert = new Insert($database, UserFixture::class, EntitySchemaFixture::create(), $this->createSchemaProvider());
        $pdo = $database->getPdo();

        $userA = UserFixture::create(email: 'a@example.com');
        $userB = UserFixture::create(email: 'b@example.com');
        $insert->entity($userA)->entity($userB)->execute();

        self::assertSame(1, $userA->id);
        self::assertSame(2, $userB->id);
        self::assertSame(['a@example.com', 'b@example.com'], array_column($insert->getExtractedValues(), 'email'));
        self::assertSame('a@example.com', $this->fetchEmail($pdo, 1));
        self::assertSame('b@example.com', $this->fetchEmail($pdo, 2));
    }

    public function testExecuteWithReturningFailsWhenNoIdIsReturned(): void
    {
        $statement = $this::createStub(PDOStatement::class);
        $statement->method('fetchAll')->willReturn([['id' => 1]]);
        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getIdentifierQuoteChar')->willReturn('"');
        $database->method('getInsertReturningClause')->willReturn('RETURNING "id"');
        $database->method('execute')->willReturn($statement);

        $insert = new Insert($database, UserFixture::class, EntitySchemaFixture::create(), $this->createSchemaProvider());
        $insert->entity(UserFixture::create(email: 'a@example.com'))->entity(UserFixture::create(email: 'b@example.com'));

        // Two rows were inserted, but only one id came back.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insert did not return a value for "id".');
        $insert->execute();
    }

    public function testExecuteWithoutReturningAssignsIdsFromLastInsertId(): void
    {
        // MySQL semantics: lastInsertId() returns the id of the FIRST row of a
        // multi-row insert; the remaining ids are derived by row offset.
        $pdoStatement = $this::createStub(PDOStatement::class);
        $pdo = $this::createStub(PDO::class);
        $pdo->method('prepare')->willReturn($pdoStatement);
        $pdo->method('lastInsertId')->willReturn('10');

        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($pdo);
        $database->method('getIdentifierQuoteChar')->willReturn('`');
        $database->method('getInsertReturningClause')->willReturn('');

        $insert = new Insert($database, UserFixture::class, EntitySchemaFixture::create(), $this->createSchemaProvider());

        $userA = UserFixture::create(email: 'a@example.com');
        $userB = UserFixture::create(email: 'b@example.com');
        $insert->entity($userA)->entity($userB)->execute();

        self::assertSame(10, $userA->id);
        self::assertSame(11, $userB->id);
    }

    public function testPrimaryKeyThatIsNotAutoIncrementIsSentAndKept(): void
    {
        $database = new SqliteDatabase(':memory:');
        $pdo = $database->getPdo();
        $pdo->exec('CREATE TABLE codes (id INTEGER PRIMARY KEY, code TEXT NOT NULL)');

        $schema = new SchemaBuilder()->addEntityPath(__DIR__ . '/../Fixtures/Entity')->build();
        $insert = new Insert($database, Code::class, $schema->entities[Code::class], new SchemaProvider($schema));
        $code = new Code(7, Uuid::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'));

        self::assertSame('INSERT INTO "codes" ("id","code") VALUES (?,?)', $insert->entity($code)->getSql());
        $insert->execute();

        self::assertSame(7, $code->id);
        $statement = $pdo->query('SELECT id FROM codes');
        self::assertNotFalse($statement);
        self::assertSame(7, $statement->fetchColumn());
    }

    private function fetchEmail(PDO $pdo, int $id): mixed
    {
        $statement = $pdo->prepare('SELECT email FROM users WHERE id=?');
        $statement->execute([$id]);
        return $statement->fetchColumn();
    }

    private function createSqliteDatabase(): SqliteDatabase
    {
        $database = new SqliteDatabase(':memory:');
        $database->getPdo()->exec(
            'CREATE TABLE users ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . 'created_at TEXT NOT NULL,'
            . 'first_name TEXT NOT NULL,'
            . 'middle_name TEXT,'
            . 'last_name TEXT NOT NULL,'
            . 'email TEXT NOT NULL,'
            . 'is_active INTEGER NOT NULL,'
            . 'type TEXT NOT NULL'
            . ')',
        );

        return $database;
    }

    private function createSchemaProvider(): SchemaProvider
    {
        return new SchemaProvider(new Schema([UserFixture::class => EntitySchemaFixture::create()]));
    }
}
