<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Database;

use MarekSkopal\ORM\Database\AbstractDatabase;
use MarekSkopal\ORM\Database\SqliteDatabase;
use MarekSkopal\ORM\Utils\QuoteUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqliteDatabase::class)]
#[UsesClass(AbstractDatabase::class)]
#[UsesClass(QuoteUtils::class)]
final class SqliteDatabaseTest extends TestCase
{
    public function testGetIdentifierQuoteChar(): void
    {
        self::assertSame('"', new SqliteDatabase(':memory:')->getIdentifierQuoteChar());
    }

    public function testGetInsertReturningClause(): void
    {
        self::assertSame('RETURNING "id"', new SqliteDatabase(':memory:')->getInsertReturningClause('id'));
    }
}
