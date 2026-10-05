<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Database;

use PDO;
use PDOStatement;

/**
 * Counts prepared statements on a live connection. Every ORM query is one prepare, so the count is
 * the number of queries sent:
 *
 *     CountingStatement::attach($pdo);
 *     CountingStatement::$count = 0;
 */
final class CountingStatement extends PDOStatement
{
    public static int $count = 0;

    protected function __construct()
    {
        self::$count++;
    }

    public static function attach(PDO $pdo): void
    {
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [self::class, []]);
        self::$count = 0;
    }
}
