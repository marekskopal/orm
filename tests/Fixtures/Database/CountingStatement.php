<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Database;

use PDO;
use PDOStatement;

/**
 * Counts statements on a live connection: $count and $queries record every execution (the
 * queries sent), $prepares every prepare (which the statement cache avoids for repeated SQL).
 *
 *     CountingStatement::attach($pdo);
 *     CountingStatement::reset();
 */
final class CountingStatement extends PDOStatement
{
    public static int $count = 0;

    public static int $prepares = 0;

    /** @var list<string> the SQL of each execution */
    public static array $queries = [];

    protected function __construct()
    {
        self::$prepares++;
    }

    /** @param array<mixed>|null $params */
    public function execute(?array $params = null): bool
    {
        self::$count++;
        self::$queries[] = $this->queryString;

        return parent::execute($params);
    }

    public static function attach(PDO $pdo): void
    {
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [self::class, []]);
        self::reset();
    }

    public static function reset(): void
    {
        self::$count = 0;
        self::$prepares = 0;
        self::$queries = [];
    }
}
