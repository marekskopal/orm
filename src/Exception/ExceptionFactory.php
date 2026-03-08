<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Exception;

class ExceptionFactory
{
    public static function create(\Throwable $exception, ?string $query = null): ORMException
    {
        if ($query === null) {
            return new ORMException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        if (str_starts_with((string) $exception->getCode(), '23')) {
            return new ConstrainException($exception, $query);
        }

        return new QueryException($exception, $query);
    }
}
