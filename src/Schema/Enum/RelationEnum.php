<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Enum;

enum RelationEnum
{
    case OneToMany;
    case ManyToOne;
    case OneToOne;
    case OneToOneInverse;
    case ManyToMany;
    case ManyToManyInverse;
}
