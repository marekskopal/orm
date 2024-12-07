<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Builder;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Enum\CaseEnum;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Utils\CaseUtils;
use ReflectionAttribute;
use ReflectionNamedType;
use ReflectionProperty;

class ColumnSchemaFactory
{
    public function create(ReflectionProperty $reflectionProperty, CaseEnum $columnCase): ColumnSchema
    {
        $attributes = $reflectionProperty->getAttributes();
        foreach ($attributes as $attribute) {
            switch ($attribute->getName()) {
                case Column::class:
                    /** @var ReflectionAttribute<Column> $attribute */
                    return $this->createFromColumnAttribute($attribute, $reflectionProperty, $columnCase);
                case ManyToOne::class:
                    /** @var ReflectionAttribute<ManyToOne> $attribute */
                    return $this->createFromManyToOneAttribute($attribute, $reflectionProperty, $columnCase);
                case OneToMany::class:
                    /** @var ReflectionAttribute<OneToMany> $attribute */
                    return $this->createFromOneToManyAttribute($attribute, $reflectionProperty, $columnCase);
            }
        }

        throw new \RuntimeException('Column attribute not found');
    }

    /** @param ReflectionAttribute<Column> $attribute */
    private function createFromColumnAttribute(
        ReflectionAttribute $attribute,
        ReflectionProperty $reflectionProperty,
        CaseEnum $columnCase,
    ): ColumnSchema
    {
        $attributeInstance = $attribute->newInstance();

        return new ColumnSchema(
            propertyName: $attributeInstance->name ?? $reflectionProperty->getName(),
            propertyType: $this->getPropertyTypeFromReflectionProperty($reflectionProperty),
            columnName: CaseUtils::toCase($columnCase, $reflectionProperty->getName()),
            columnType: $attributeInstance->type,
            isPrimary: $attributeInstance->primary,
            isNullable: $attributeInstance->nullable,
            enumClass: $attributeInstance->enum,
            extensionClass: $attributeInstance->extension,
            extensionOptions: $attributeInstance->extensionOptions,
        );
    }

    /** @param ReflectionAttribute<ManyToOne> $attribute */
    private function createFromManyToOneAttribute(
        ReflectionAttribute $attribute,
        ReflectionProperty $reflectionProperty,
        CaseEnum $columnCase,
    ): ColumnSchema
    {
        $attributeInstance = $attribute->newInstance();

        return new ColumnSchema(
            propertyName: $reflectionProperty->getName(),
            propertyType: PropertyTypeEnum::Relation,
            columnName: $attributeInstance->name ?? CaseUtils::toCase($columnCase, $reflectionProperty->getName() . 'Id'),
            columnType: 'int',
            relationType: RelationEnum::ManyToOne,
            relationEntityClass: $attributeInstance->entityClass,
        );
    }

    /** @param ReflectionAttribute<OneToMany> $attribute */
    private function createFromOneToManyAttribute(
        ReflectionAttribute $attribute,
        ReflectionProperty $reflectionProperty,
        CaseEnum $columnCase,
    ): ColumnSchema
    {
        $attributeInstance = $attribute->newInstance();

        return new ColumnSchema(
            propertyName: $reflectionProperty->getName(),
            propertyType: PropertyTypeEnum::Relation,
            columnName: CaseUtils::toCase($columnCase, $reflectionProperty->getName()),
            columnType: 'int',
            relationType: RelationEnum::OneToMany,
            relationEntityClass: $attributeInstance->entityClass,
        );
    }

    private function getPropertyTypeFromReflectionProperty(ReflectionProperty $reflectionProperty): PropertyTypeEnum
    {
        $type = $reflectionProperty->getType();
        if (!($type instanceof ReflectionNamedType)) {
            throw new \RuntimeException('Property type is not named');
        }

        if ($type->isBuiltin()) {
            return PropertyTypeEnum::fromTypeName($type->getName());
        }

        return PropertyTypeEnum::fromClassName($type->getName());
    }
}
