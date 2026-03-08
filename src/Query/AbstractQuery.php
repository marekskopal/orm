<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;

abstract class AbstractQuery implements QueryInterface
{
    protected readonly PDO $pdo;

    protected readonly string $identifierQuoteChar;

    protected readonly DatabaseInterface $database;

    public function __construct(
        DatabaseInterface $database,
        protected readonly string $entityClass,
        protected readonly EntitySchema $schema,
    ) {
        $this->database = $database;
        $this->pdo = $database->getPdo();
        $this->identifierQuoteChar = $database->getIdentifierQuoteChar();
    }

    abstract public function getSql(): string;

    protected function escape(string $name): string
    {
        return $this->identifierQuoteChar . $name . $this->identifierQuoteChar;
    }
}
