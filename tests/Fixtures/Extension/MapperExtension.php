<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Extension;

use MarekSkopal\ORM\Mapper\MapperInterface;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Utils\ValidationUtils;

final class MapperExtension implements MapperInterface
{
    public function mapToProperty(EntitySchema $entitySchema, ColumnSchema $columnSchema, string|int|float|null $value,): float
    {
        return ValidationUtils::checkFloat($value) + 1;
    }

    public function mapToColumn(ColumnSchema $columnSchema, string|int|float|bool|object|null $value): float
    {
        return ValidationUtils::checkFloat($value) + 1;
    }
}
