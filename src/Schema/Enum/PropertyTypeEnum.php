<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Enum;

enum PropertyTypeEnum
{
    case String;
    case Int;
    case Float;
    case Bool;
    case Relation;
}
