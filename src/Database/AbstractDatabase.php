<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Database;

use MarekSkopal\ORM\Exception\ExceptionFactory;
use PDO;
use PDOException;
use PDOStatement;
use SensitiveParameter;

/**
 * A lazily opened PDO connection with a statement cache.
 *
 * The constructor only stores the connection settings: an ORM that never queries never connects.
 * Statements are prepared once per SQL string and kept in a least-recently-used map, so repeated
 * query shapes (identity lookups, batch loads, inserts of one class) skip the prepare round trip.
 */
abstract class AbstractDatabase implements DatabaseInterface
{
    public const int DefaultStatementCacheSize = 256;

    private ?PDO $pdo = null;

    /** @var array<string, PDOStatement> SQL => statement, least recently used first */
    private array $statements = [];

    public function __construct(
        #[SensitiveParameter] protected ?string $username = null,
        #[SensitiveParameter] protected ?string $password = null,
        private readonly int $statementCacheSize = self::DefaultStatementCacheSize,
    ) {
        if ($statementCacheSize < 0) {
            throw new \InvalidArgumentException('The statement cache size cannot be negative.');
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo ??= new PDO($this->getDsn(), $this->username, $this->password, $this->getOptions());
    }

    public function connect(): void
    {
        $this->getPdo();
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /** @param list<mixed> $params */
    public function execute(string $sql, array $params = [], bool $cached = true): PDOStatement
    {
        try {
            $statement = $cached ? $this->prepareCached($sql) : $this->getPdo()->prepare($sql);
            $statement->execute($params);

            return $statement;
        } catch (PDOException $e) {
            throw ExceptionFactory::create($e, $sql);
        }
    }

    public function prepareCached(string $sql): PDOStatement
    {
        if ($this->statementCacheSize === 0) {
            return $this->getPdo()->prepare($sql);
        }

        $statement = $this->statements[$sql] ?? null;
        if ($statement !== null) {
            // Move to the most recently used end, and release rows a previous caller left unread.
            unset($this->statements[$sql]);
            $this->statements[$sql] = $statement;
            $statement->closeCursor();

            return $statement;
        }

        $statement = $this->getPdo()->prepare($sql);
        if (count($this->statements) >= $this->statementCacheSize) {
            unset($this->statements[array_key_first($this->statements)]);
        }

        $this->statements[$sql] = $statement;

        return $statement;
    }

    public function clearStatementCache(): void
    {
        $this->statements = [];
    }

    abstract protected function getDsn(): string;

    /** @return array<int, mixed> */
    protected function getOptions(): array
    {
        return [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    }
}
