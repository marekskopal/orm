<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query\Model;

readonly class Join
{
    public function __construct(
        public string $tableAlias,
        public string $column,
        public string $referenceTable,
        public string $refenceTableAlias,
        public string $referenceColumn,
    )
    {
    }
}
