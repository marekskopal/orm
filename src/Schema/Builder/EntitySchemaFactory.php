<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Builder;

use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Repository\Repository;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\CaseEnum;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use ReflectionClass;

class EntitySchemaFactory
{
    /** @param ReflectionClass<object> $reflectionClass */
    public function create(ReflectionClass $reflectionClass, CaseEnum $tableCase, CaseEnum $columnCase): EntitySchema
    {
        $attributes = $reflectionClass->getAttributes(Entity::class);
        $attribute = $attributes[0]->newInstance();

        $columns = [];
        $properties = $reflectionClass->getProperties();
        foreach ($properties as $property) {
            try {
                $columns[$property->getName()] = new ColumnSchemaFactory()->create($property, $columnCase);
            } catch (\RuntimeException) {
                throw new \RuntimeException(
                    sprintf(
                        'Column attribute not found on property "%s" on class "%s".',
                        $property->getName(),
                        $reflectionClass->getName(),
                    ),
                );
            }
        }

        return new EntitySchema(
            entityClass: $reflectionClass->getName(),
            repositoryClass: $attribute->repositoryClass ?? Repository::class,
            table: $attribute->table ?? NameUtils::getTableName(CaseUtils::toCase($tableCase, $reflectionClass->getShortName())),
            columns: $columns,
        );
    }
}
