<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Enum;

enum CascadeEnum: string
{
    case Persist = 'persist';
    case Remove = 'remove';
}
