<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Entity\Enum;

enum UserTypeEnum: string
{
    case Admin = 'admin';
    case User = 'user';
}
