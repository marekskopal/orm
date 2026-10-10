<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use MarekSkopal\ORM\Database\AbstractDatabase;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Database\SqliteDatabase;
use MarekSkopal\ORM\Mapper\ExtensionMapperProvider;
use MarekSkopal\ORM\Query\Update;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Compiler\CodeExporter;
use MarekSkopal\ORM\Schema\Compiler\ExtractorGenerator;
use MarekSkopal\ORM\Schema\Compiler\HydratorGenerator;
use MarekSkopal\ORM\Schema\Compiler\NormalizerGenerator;
use MarekSkopal\ORM\Schema\Compiler\SchemaCompiler;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\EntitySchemaFixture;
use MarekSkopal\ORM\Utils\NameUtils;
use MarekSkopal\ORM\Utils\QuoteUtils;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Update::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(NameUtils::class)]
#[UsesClass(AbstractDatabase::class)]
#[UsesClass(SqliteDatabase::class)]
#[UsesClass(SchemaProvider::class)]
#[UsesClass(Schema::class)]
#[UsesClass(SchemaCompiler::class)]
#[UsesClass(ExtractorGenerator::class)]
#[UsesClass(CodeExporter::class)]
#[UsesClass(ExtensionMapperProvider::class)]
#[UsesClass(HydratorGenerator::class)]
#[UsesClass(NormalizerGenerator::class)]
#[UsesClass(QuoteUtils::class)]
final class UpdateTest extends TestCase
{
    public function testGetSql(): void
    {
        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($this::createStub(PDO::class));
        $database->method('getIdentifierQuoteChar')->willReturn('`');
        $entitySchema = EntitySchemaFixture::create();

        $insert = new Update(
            $database,
            UserFixture::class,
            $entitySchema,
            new SchemaProvider(new Schema([UserFixture::class => $entitySchema])),
        );
        $insert->entity(UserFixture::create());

        self::assertSame(
            'UPDATE `users` SET `created_at`=?,`first_name`=?,`middle_name`=?,`last_name`=?,`email`=?,`is_active`=?,`type`=? WHERE `id`=?',
            $insert->getSql(),
        );
    }

    public function testExecuteBindsExtractedValuesAndPrimaryKey(): void
    {
        $database = new SqliteDatabase(':memory:');
        $pdo = $database->getPdo();
        $pdo->exec(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, created_at TEXT, first_name TEXT, middle_name TEXT, last_name TEXT,'
            . ' email TEXT, is_active INTEGER, type TEXT)',
        );
        $pdo->exec("INSERT INTO users VALUES (5, '2020-01-01 00:00:00', 'Old', NULL, 'Old', 'old@example.com', 0, 'user')");

        $entitySchema = EntitySchemaFixture::create();

        $user = UserFixture::create(firstName: 'Jane', middleName: 'J', isActive: true, type: UserTypeEnum::Admin);
        $user->id = 5;
        new Update($database, UserFixture::class, $entitySchema, new SchemaProvider(new Schema([UserFixture::class => $entitySchema])))
            ->entity($user)
            ->execute();

        $statement = $pdo->query('SELECT created_at, first_name, middle_name, is_active, type FROM users WHERE id = 5');
        self::assertNotFalse($statement);
        self::assertSame(
            ['created_at' => '2024-01-01 00:00:00', 'first_name' => 'Jane', 'middle_name' => 'J', 'is_active' => 1, 'type' => 'admin'],
            $statement->fetch(PDO::FETCH_ASSOC),
        );
    }

    public function testValuesRestrictTheUpdateToGivenColumns(): void
    {
        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($this::createStub(PDO::class));
        $database->method('getIdentifierQuoteChar')->willReturn('`');
        $entitySchema = EntitySchemaFixture::create();

        $update = new Update(
            $database,
            UserFixture::class,
            $entitySchema,
            new SchemaProvider(new Schema([UserFixture::class => $entitySchema])),
        );
        $update->entity(UserFixture::create())->values(['email' => 'a@example.com']);

        self::assertSame('UPDATE `users` SET `email`=? WHERE `id`=?', $update->getSql());
    }

    public function testEmptyValuesExecuteNothing(): void
    {
        $database = $this->createMock(DatabaseInterface::class);
        $database->expects($this->never())->method('execute');
        $entitySchema = EntitySchemaFixture::create();

        new Update($database, UserFixture::class, $entitySchema, new SchemaProvider(new Schema([UserFixture::class => $entitySchema])))
            ->entity(UserFixture::create())
            ->values([])
            ->execute();
    }

    public function testGetSqlNoEntities(): void
    {
        $this->expectException(\LogicException::class);

        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($this::createStub(PDO::class));
        $database->method('getIdentifierQuoteChar')->willReturn('`');
        $entitySchema = EntitySchemaFixture::create();

        $insert = new Update(
            $database,
            UserFixture::class,
            $entitySchema,
            new SchemaProvider(new Schema([UserFixture::class => $entitySchema])),
        );

        $insert->getSql();
    }
}
