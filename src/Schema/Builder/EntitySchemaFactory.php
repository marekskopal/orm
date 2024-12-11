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
    private array $tableAliases = [];

    /** @param ReflectionClass<object> $reflectionClass */
    public function create(ReflectionClass $reflectionClass, CaseEnum $tableCase, CaseEnum $columnCase): EntitySchema
    {
        $attributes = $reflectionClass->getAttributes(Entity::class);
        $attribute = $attributes[0]->newInstance();

        $columnSchemaFactory = new ColumnSchemaFactory($reflectionClass);

        $columns = [];
        $properties = $reflectionClass->getProperties();
        foreach ($properties as $property) {
            try {
                $columns[$property->getName()] = $columnSchemaFactory->create($property, $columnCase);
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

        $table = $attribute->table ?? NameUtils::getTableName(CaseUtils::toCase($tableCase, $reflectionClass->getShortName()));

        return new EntitySchema(
            entityClass: $reflectionClass->getName(),
            repositoryClass: $attribute->repositoryClass ?? Repository::class,
            table: $table,
            tableAlias: $this->getTableAlias($table),
            columns: $columns,
        );
    }

    private function getTableAlias(string $table): string
    {
        for ($i = 0; $i < strlen($table); $i++) {
            $alias = substr($table, 0, $i + 1);

            if (!isset($this->tableAliases[$alias])) {
                $this->tableAliases[$alias] = $table;
                return $alias;
            }
        }

        return $table;
    }
}
