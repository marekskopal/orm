<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Enum;

enum PropertyTypeEnum: string
{
    case String = 'string';
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
}
