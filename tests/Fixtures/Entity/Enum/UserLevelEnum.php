<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity\Enum;

enum UserLevelEnum: int
{
    case Basic = 1;
    case Premium = 2;
}
