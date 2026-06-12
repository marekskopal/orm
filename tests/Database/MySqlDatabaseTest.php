<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Database;

use MarekSkopal\ORM\Database\MySqlDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(MySqlDatabase::class)]
final class MySqlDatabaseTest extends TestCase
{
    public function testGetDsnContainsCharset(): void
    {
        $database = $this->createDatabase();

        self::assertSame('mysql:host=localhost;dbname=orm;charset=utf8mb4', $this->invokeGetDsn($database));
    }

    public function testGetDsnWithCustomCharset(): void
    {
        $database = $this->createDatabase(charset: 'utf8');

        self::assertSame('mysql:host=localhost;dbname=orm;charset=utf8', $this->invokeGetDsn($database));
    }

    public function testGetOptionsDisablesEmulatedPrepares(): void
    {
        $database = $this->createDatabase();

        $reflectionClass = new ReflectionClass(MySqlDatabase::class);
        /** @var array<int, mixed> $options */
        $options = $reflectionClass->getMethod('getOptions')->invoke($database);

        self::assertFalse($options[PDO::ATTR_EMULATE_PREPARES]);
        self::assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
    }

    public function testGetIdentifierQuoteChar(): void
    {
        self::assertSame('`', $this->createDatabase()->getIdentifierQuoteChar());
    }

    public function testGetInsertReturningClause(): void
    {
        self::assertSame('', $this->createDatabase()->getInsertReturningClause('id'));
    }

    /**
     * The constructor connects immediately, so the instance is created without
     * the constructor and the readonly properties are initialized via reflection.
     */
    private function createDatabase(string $charset = 'utf8mb4'): MySqlDatabase
    {
        $reflectionClass = new ReflectionClass(MySqlDatabase::class);
        $database = $reflectionClass->newInstanceWithoutConstructor();

        $reflectionClass->getProperty('host')->setValue($database, 'localhost');
        $reflectionClass->getProperty('database')->setValue($database, 'orm');
        $reflectionClass->getProperty('charset')->setValue($database, $charset);

        return $database;
    }

    private function invokeGetDsn(MySqlDatabase $database): string
    {
        $reflectionClass = new ReflectionClass(MySqlDatabase::class);
        /** @var string $dsn */
        $dsn = $reflectionClass->getMethod('getDsn')->invoke($database);
        return $dsn;
    }
}
