<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Database;

use MarekSkopal\ORM\Utils\QuoteUtils;
use PDO;

class PostgresDatabase extends AbstractDatabase
{
    public function __construct(
        private readonly string $host,
        string $username,
        string $password,
        private readonly string $database,
        private readonly int $port = 5432,
        int $statementCacheSize = self::DefaultStatementCacheSize,
    ) {
        parent::__construct($username, $password, $statementCacheSize);
    }

    protected function getDsn(): string
    {
        return 'pgsql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->database;
    }

    public function getIdentifierQuoteChar(): string
    {
        return '"';
    }

    public function getInsertReturningClause(string $primaryColumnName): string
    {
        return 'RETURNING ' . QuoteUtils::quote($primaryColumnName, $this->getIdentifierQuoteChar());
    }

    /** @return array<int, mixed> */
    protected function getOptions(): array
    {
        // array_replace, not spread: spreading re-indexes the integer PDO::ATTR_* keys
        return array_replace(parent::getOptions(), [PDO::ATTR_EMULATE_PREPARES => false]);
    }
}
