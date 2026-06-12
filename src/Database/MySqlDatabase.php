<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Database;

use PDO;

readonly class MySqlDatabase extends AbstractDatabase
{
    public function __construct(
        private string $host,
        string $username,
        string $password,
        private string $database,
        private string $charset = 'utf8mb4',
    ) {
        parent::__construct($username, $password);
    }

    protected function getDsn(): string
    {
        return 'mysql:host=' . $this->host . ';dbname=' . $this->database . ';charset=' . $this->charset;
    }

    public function getIdentifierQuoteChar(): string
    {
        return '`';
    }

    public function getInsertReturningClause(string $primaryColumnName): string
    {
        return '';
    }

    /** @return array<int, mixed> */
    protected function getOptions(): array
    {
        // array_replace, not spread: spreading re-indexes the integer PDO::ATTR_* keys
        return array_replace(parent::getOptions(), [PDO::ATTR_EMULATE_PREPARES => false]);
    }
}
