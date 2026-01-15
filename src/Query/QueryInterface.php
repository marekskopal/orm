<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

interface QueryInterface
{
    public function getSql(): string;
}
