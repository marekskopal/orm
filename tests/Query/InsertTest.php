<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use DateTimeImmutable;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Query\Insert;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\EntitySchemaFixture;
use MarekSkopal\ORM\Utils\NameUtils;
use MarekSkopal\ORM\Utils\QuoteUtils;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Insert::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(NameUtils::class)]
#[UsesClass(QuoteUtils::class)]
final class InsertTest extends TestCase
{
    public function testGetSql(): void
    {
        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($this::createStub(PDO::class));
        $database->method('getIdentifierQuoteChar')->willReturn('`');
        $entitySchema = EntitySchemaFixture::create();
        $mapper = $this::createStub(Mapper::class);

        $insert = new Insert($database, UserFixture::class, $entitySchema, $mapper);
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
        $mapper = $this::createStub(Mapper::class);

        $insert = new Insert($database, UserFixture::class, $entitySchema, $mapper);
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
        $mapper = $this::createStub(Mapper::class);

        $insert = new Insert($database, UserFixture::class, $entitySchema, $mapper);

        $insert->getSql();
    }

    public function testExecuteNoEntities(): void
    {
        $this->expectException(\LogicException::class);

        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($this::createStub(PDO::class));
        $database->method('getIdentifierQuoteChar')->willReturn('`');
        $entitySchema = EntitySchemaFixture::create();
        $mapper = $this::createStub(Mapper::class);

        $insert = new Insert($database, UserFixture::class, $entitySchema, $mapper);

        $insert->execute();
    }

    public function testExecuteWithReturningAssignsIds(): void
    {
        $pdo = $this->createSqlitePdo();
        $insert = $this->createSqliteInsert($pdo, returningClause: 'RETURNING "id"');

        $userA = UserFixture::create(email: 'a@example.com');
        $userB = UserFixture::create(email: 'b@example.com');
        $insert->entity($userA)->entity($userB)->execute();

        self::assertSame(1, $userA->id);
        self::assertSame(2, $userB->id);
        self::assertSame('a@example.com', $this->fetchEmail($pdo, 1));
        self::assertSame('b@example.com', $this->fetchEmail($pdo, 2));
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

        $mapper = $this::createStub(Mapper::class);

        $insert = new Insert($database, UserFixture::class, EntitySchemaFixture::create(), $mapper);

        $userA = UserFixture::create(email: 'a@example.com');
        $userB = UserFixture::create(email: 'b@example.com');
        $insert->entity($userA)->entity($userB)->execute();

        self::assertSame(10, $userA->id);
        self::assertSame(11, $userB->id);
    }

    private function fetchEmail(PDO $pdo, int $id): mixed
    {
        $statement = $pdo->prepare('SELECT email FROM users WHERE id=?');
        $statement->execute([$id]);
        return $statement->fetchColumn();
    }

    private function createSqlitePdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(
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

        return $pdo;
    }

    /** @return Insert<UserFixture> */
    private function createSqliteInsert(PDO $pdo, string $returningClause): Insert
    {
        $database = $this::createStub(DatabaseInterface::class);
        $database->method('getPdo')->willReturn($pdo);
        $database->method('getIdentifierQuoteChar')->willReturn('"');
        $database->method('getInsertReturningClause')->willReturn($returningClause);

        $mapper = $this::createStub(Mapper::class);
        $mapper->method('mapToColumn')->willReturnCallback(
            static function (ColumnSchema $column, string|int|float|bool|object|null $value): string|int|float|null {
                if ($value instanceof DateTimeImmutable) {
                    return $value->format('Y-m-d H:i:s');
                }

                if ($value instanceof UserTypeEnum) {
                    return $value->value;
                }

                if (is_bool($value)) {
                    return (int) $value;
                }

                if (is_object($value)) {
                    throw new \InvalidArgumentException('Unsupported value type');
                }

                return $value;
            },
        );

        return new Insert($database, UserFixture::class, EntitySchemaFixture::create(), $mapper);
    }
}
