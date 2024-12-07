<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query\Enum;

enum DirectionEnum: string
{
    case Asc = 'ASC';
    case Desc = 'DESC';
}
