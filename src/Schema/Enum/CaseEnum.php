<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Enum;

enum CaseEnum: string
{
    case SnakeCase = 'snake_case';
    case CamelCase = 'camelCase';
}
