<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Database;

readonly class MySqlDatabase extends AbstractDatabase
{
    public function __construct(private string $host, string $username, string $password, private string $database,)
    {
        parent::__construct($username, $password);
    }

    protected function getDsn(): string
    {
        return 'mysql:host=' . $this->host . ';dbname=' . $this->database;
    }
}
