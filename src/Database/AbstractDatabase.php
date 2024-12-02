<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Database;

use PDO;
use SensitiveParameter;

abstract readonly class AbstractDatabase implements DatabaseInterface
{
    protected PDO $pdo;

    public function __construct(
        #[SensitiveParameter] protected ?string $username = null,
        #[SensitiveParameter] protected ?string $password = null,
    ) {
        $this->pdo = new PDO($this->getDsn(), $this->username, $this->password);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    abstract protected function getDsn(): string;
}
