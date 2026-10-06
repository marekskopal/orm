<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Database;

use PDO;
use PDOStatement;

interface DatabaseInterface
{
    /** The PDO connection, opened on first call. */
    public function getPdo(): PDO;

    /** Opens the connection if it is not open yet; connection errors surface here. */
    public function connect(): void;

    public function isConnected(): bool;

    /**
     * Prepares (or reuses a prepared statement for) the SQL and executes it with the parameters.
     * Driver errors are thrown as QueryException or ConstrainException carrying the SQL.
     *
     * A cached statement is shared by every caller with the same SQL, so read its results fully
     * before executing the same SQL again; pass $cached = false for a result read incrementally.
     *
     * @param list<mixed> $params
     */
    public function execute(string $sql, array $params = [], bool $cached = true): PDOStatement;

    /** A prepared statement for the SQL, reused from a bounded least-recently-used cache. */
    public function prepareCached(string $sql): PDOStatement;

    /** Drops cached statements, e.g. after schema changes that invalidate them. */
    public function clearStatementCache(): void;

    public function getIdentifierQuoteChar(): string;

    public function getInsertReturningClause(string $primaryColumnName): string;
}
