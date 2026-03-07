<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Database;

use PDO;

interface DatabaseInterface
{
    public function getPdo(): PDO;

    public function getIdentifierQuoteChar(): string;
}
