<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use MarekSkopal\ORM\Schema\EntitySchema;
use PDO;

abstract class AbstractQuery implements QueryInterface
{
    public function __construct(
        protected readonly PDO $pdo,
        protected readonly string $entityClass,
        protected readonly EntitySchema $schema,
    ) {
    }

    abstract public function getSql(): string;
}
