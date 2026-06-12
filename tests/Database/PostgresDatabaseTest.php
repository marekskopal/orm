<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Database;

use MarekSkopal\ORM\Database\PostgresDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(PostgresDatabase::class)]
final class PostgresDatabaseTest extends TestCase
{
    public function testGetDsn(): void
    {
        $database = $this->createDatabase();

        $reflectionClass = new ReflectionClass(PostgresDatabase::class);
        /** @var string $dsn */
        $dsn = $reflectionClass->getMethod('getDsn')->invoke($database);

        self::assertSame('pgsql:host=localhost;port=5432;dbname=orm', $dsn);
    }

    public function testGetOptionsDisablesEmulatedPrepares(): void
    {
        $database = $this->createDatabase();

        $reflectionClass = new ReflectionClass(PostgresDatabase::class);
        /** @var array<int, mixed> $options */
        $options = $reflectionClass->getMethod('getOptions')->invoke($database);

        self::assertFalse($options[PDO::ATTR_EMULATE_PREPARES]);
        self::assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
    }

    public function testGetIdentifierQuoteChar(): void
    {
        self::assertSame('"', $this->createDatabase()->getIdentifierQuoteChar());
    }

    public function testGetInsertReturningClause(): void
    {
        self::assertSame('RETURNING "id"', $this->createDatabase()->getInsertReturningClause('id'));
    }

    /**
     * The constructor connects immediately, so the instance is created without
     * the constructor and the readonly properties are initialized via reflection.
     */
    private function createDatabase(): PostgresDatabase
    {
        $reflectionClass = new ReflectionClass(PostgresDatabase::class);
        $database = $reflectionClass->newInstanceWithoutConstructor();

        $reflectionClass->getProperty('host')->setValue($database, 'localhost');
        $reflectionClass->getProperty('database')->setValue($database, 'orm');
        $reflectionClass->getProperty('port')->setValue($database, 5432);

        return $database;
    }
}
