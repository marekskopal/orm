<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Database;

use MarekSkopal\ORM\Utils\QuoteUtils;

class SqliteDatabase extends AbstractDatabase
{
    public function __construct(private readonly string $path, int $statementCacheSize = self::DefaultStatementCacheSize)
    {
        parent::__construct(statementCacheSize: $statementCacheSize);
    }

    protected function getDsn(): string
    {
        return 'sqlite:' . $this->path;
    }

    public function getIdentifierQuoteChar(): string
    {
        return '"';
    }

    public function getInsertReturningClause(string $primaryColumnName): string
    {
        // Supported since SQLite 3.35 (2021-03); PHP 8.4 bundles a newer version.
        return 'RETURNING ' . QuoteUtils::quote($primaryColumnName, $this->getIdentifierQuoteChar());
    }
}
