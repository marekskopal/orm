<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Database;

use MarekSkopal\ORM\Database\AbstractDatabase;
use MarekSkopal\ORM\Database\MySqlDatabase;
use MarekSkopal\ORM\Database\SqliteDatabase;
use MarekSkopal\ORM\Exception\ConstrainException;
use MarekSkopal\ORM\Exception\ExceptionFactory;
use MarekSkopal\ORM\Exception\QueryException;
use MarekSkopal\ORM\Tests\Fixtures\Database\CountingStatement;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractDatabase::class)]
#[UsesClass(SqliteDatabase::class)]
#[UsesClass(MySqlDatabase::class)]
#[UsesClass(ExceptionFactory::class)]
#[UsesClass(QueryException::class)]
#[UsesClass(ConstrainException::class)]
final class AbstractDatabaseTest extends TestCase
{
    public function testConstructingDoesNotConnect(): void
    {
        // Nothing listens on port 1, so connecting would fail; constructing must not try.
        $database = new MySqlDatabase('127.0.0.1', 'nobody', 'secret', 'none', port: 1);
        self::assertFalse($database->isConnected());

        $sqlite = new SqliteDatabase(':memory:');
        self::assertFalse($sqlite->isConnected());
        $sqlite->connect();
        self::assertTrue($sqlite->isConnected());
        self::assertSame($sqlite->getPdo(), $sqlite->getPdo());

        $this->expectException(\PDOException::class);
        $database->connect();
    }

    public function testSameSqlIsPreparedOnce(): void
    {
        $database = $this->createDatabase();

        for ($i = 1; $i <= 3; $i++) {
            self::assertSame((string) $i, (string) $database->execute('SELECT ? AS v', [$i])->fetchColumn());
        }

        self::assertSame(1, CountingStatement::$prepares);
        self::assertSame(3, CountingStatement::$count);
        self::assertSame($database->prepareCached('SELECT ? AS v'), $database->prepareCached('SELECT ? AS v'));
    }

    public function testCacheEvictsLeastRecentlyUsedAtItsBound(): void
    {
        $database = $this->createDatabase(statementCacheSize: 2);

        $database->execute('SELECT 1');
        $database->execute('SELECT 2');
        // Touch "SELECT 1" so "SELECT 2" is the least recently used.
        $database->execute('SELECT 1');
        $database->execute('SELECT 3');
        self::assertSame(3, CountingStatement::$prepares);

        $database->execute('SELECT 1');
        self::assertSame(3, CountingStatement::$prepares);

        // "SELECT 2" was evicted and is prepared again.
        $database->execute('SELECT 2');
        self::assertSame(4, CountingStatement::$prepares);
    }

    public function testCacheCanBeDisabledAndCleared(): void
    {
        $disabled = $this->createDatabase(statementCacheSize: 0);
        $disabled->execute('SELECT 1');
        $disabled->execute('SELECT 1');
        self::assertSame(2, CountingStatement::$prepares);

        $database = $this->createDatabase();
        $database->execute('SELECT 1');
        $database->clearStatementCache();
        $database->execute('SELECT 1');
        self::assertSame(2, CountingStatement::$prepares);

        $uncached = $this->createDatabase();
        $uncached->execute('SELECT 1', cached: false);
        $uncached->execute('SELECT 1', cached: false);
        self::assertSame(2, CountingStatement::$prepares);
    }

    public function testNegativeCacheSizeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SqliteDatabase(':memory:', statementCacheSize: -1);
    }

    public function testReusedStatementDiscardsRowsLeftUnread(): void
    {
        $database = $this->createDatabase();
        $database->getPdo()->exec('CREATE TABLE t (v INTEGER)');
        $database->getPdo()->exec('INSERT INTO t VALUES (1), (2), (3)');

        // Read one row and leave the rest, as fetchOne() does.
        self::assertSame(1, $database->execute('SELECT v FROM t WHERE v >= ? ORDER BY v', [1])->fetchColumn());
        self::assertSame([2, 3], $database->execute('SELECT v FROM t WHERE v >= ? ORDER BY v', [2])->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testDriverErrorsCarryTheSql(): void
    {
        $database = $this->createDatabase();
        $database->getPdo()->exec('CREATE TABLE t (v INTEGER PRIMARY KEY)');
        $database->execute('INSERT INTO t VALUES (?)', [1]);

        try {
            $database->execute('INSERT INTO t VALUES (?)', [1]);
            self::fail('Duplicate key was accepted');
        } catch (ConstrainException $e) {
            self::assertSame('INSERT INTO t VALUES (?)', $e->getQuery());
        }

        $this->expectException(QueryException::class);
        $database->execute('SELECT * FROM missing');
    }

    private function createDatabase(int $statementCacheSize = AbstractDatabase::DefaultStatementCacheSize): SqliteDatabase
    {
        $database = new SqliteDatabase(':memory:', $statementCacheSize);
        CountingStatement::attach($database->getPdo());

        return $database;
    }
}
