<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Database;

readonly class SqliteDatabase extends AbstractDatabase
{
    public function __construct(private string $path)
    {
        parent::__construct();
    }

    protected function getDsn(): string
    {
        return 'sqlite:' . $this->path;
    }
}
