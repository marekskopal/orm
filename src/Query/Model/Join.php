<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query\Model;

readonly class Join
{
    public function __construct(public string $column, public string $referenceTable, public string $referenceColumn,)
    {
    }
}
