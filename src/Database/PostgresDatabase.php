<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Database;

use PDO;

readonly class PostgresDatabase extends AbstractDatabase
{
    public function __construct(
        private string $host,
        string $username,
        string $password,
        private string $database,
        private int $port = 5432,
    ) {
        parent::__construct($username, $password);
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
        $q = $this->getIdentifierQuoteChar();
        return 'RETURNING ' . $q . $primaryColumnName . $q;
    }

    /** @return array<int, mixed> */
    protected function getOptions(): array
    {
        return [...parent::getOptions(), PDO::ATTR_EMULATE_PREPARES => false];
    }
}
