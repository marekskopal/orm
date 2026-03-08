<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Transaction;

use Closure;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Exception\TransactionException;

readonly class TransactionProvider
{
    public function __construct(private DatabaseInterface $database)
    {
    }

    public function beginTransaction(): void
    {
        $this->database->getPdo()->beginTransaction();
    }

    public function commit(): void
    {
        $this->database->getPdo()->commit();
    }

    public function rollback(): void
    {
        $this->database->getPdo()->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->database->getPdo()->inTransaction();
    }

    /**
     * Executes the given callback within a database transaction.
     * Commits on success, rolls back and re-throws on any exception.
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public function transaction(Closure $callback): mixed
    {
        if ($this->inTransaction()) {
            throw new TransactionException('A transaction is already active.');
        }

        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();

            throw $e;
        }
    }
}
