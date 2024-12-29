<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Exception;

class QueryException extends ORMException
{
    public function __construct(\Throwable $previous, private string $query)
    {
        parent::__construct($previous->getMessage(), (int) $previous->getCode(), $previous);
    }

    public function getQuery(): string
    {
        return $this->query;
    }
}
