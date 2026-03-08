<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Builder;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ForeignKey;
use MarekSkopal\ORM\Attribute\ManyToMany;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Attribute\OneToOne;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Enum\CaseEnum;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class ColumnSchemaFactory
{
    /** @param ReflectionClass<object> $reflectionClass */
    public function __construct(private readonly ReflectionClass $reflectionClass)
    {
    }

    public function create(ReflectionProperty $reflectionProperty, CaseEnum $columnCase): ColumnSchema
    {
        $attributes = $reflectionProperty->getAttributes();
        foreach ($attributes as $attribute) {
            $attributeInstance = $attribute->newInstance();
            if ($attributeInstance instanceof Column) {
                /** @var ReflectionAttribute<ForeignKey>|null $foreignKeyAttribute */
                $foreignKeyAttribute = $reflectionProperty->getAttributes(ForeignKey::class)[0] ?? null;

                /** @phpstan-var ReflectionAttribute<Column> $attribute */
                return $this->createFromColumnAttribute($attribute, $reflectionProperty, $columnCase, $foreignKeyAttribute);
            }
            if ($attributeInstance instanceof ManyToOne) {
                /** @phpstan-var ReflectionAttribute<ManyToOne> $attribute */
                return $this->createFromManyToOneAttribute($attribute, $reflectionProperty, $columnCase);
            }
            if ($attributeInstance instanceof OneToMany) {
                /** @phpstan-var ReflectionAttribute<OneToMany> $attribute */
                return $this->createFromOneToManyAttribute($attribute, $reflectionProperty, $columnCase);
            }
            if ($attributeInstance instanceof OneToOne) {
                /** @phpstan-var ReflectionAttribute<OneToOne> $attribute */
                return $this->createFromOneToOneAttribute($attribute, $reflectionProperty, $columnCase);
            }
            if ($attributeInstance instanceof ManyToMany) {
                /** @phpstan-var ReflectionAttribute<ManyToMany> $attribute */
                return $this->createFromManyToManyAttribute($attribute, $reflectionProperty, $columnCase);
            }
        }

        throw new \RuntimeException(sprintf('Column attribute not found on property "%s".', $reflectionProperty->getName()));
    }

    /**
     * @param ReflectionAttribute<Column> $attribute
     * @param ReflectionAttribute<ForeignKey>|null $foreignKeyAttribute
     */
    private function createFromColumnAttribute(
        ReflectionAttribute $attribute,
        ReflectionProperty $reflectionProperty,
        CaseEnum $columnCase,
        ?ReflectionAttribute $foreignKeyAttribute,
    ): ColumnSchema
    {
        $attributeInstance = $attribute->newInstance();
        $foreignKeyAttributeInstance = $foreignKeyAttribute?->newInstance();

        return new ColumnSchema(
            propertyName: $reflectionProperty->getName(),
            propertyType: $attributeInstance->extension !== null ? PropertyTypeEnum::Extension : $this->getPropertyTypeFromReflectionProperty(
                $reflectionProperty,
            ),
            columnName: $attributeInstance->name ?? CaseUtils::toCase($columnCase, $reflectionProperty->getName()),
            columnType: $attributeInstance->type,
            relationType: $foreignKeyAttribute !== null ? RelationEnum::ManyToOne : null,
            relationEntityClass: $foreignKeyAttributeInstance?->entityClass,
            isPrimary: $attributeInstance->primary,
            isAutoIncrement: $attributeInstance->autoIncrement,
            isNullable: $attributeInstance->nullable,
            size: $attributeInstance->size,
            precision: $attributeInstance->precision,
            scale: $attributeInstance->scale,
            default: $attributeInstance->default,
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
            columnType: Type::Int,
            isNullable: $attributeInstance->nullable,
            size: 11,
            relationType: RelationEnum::ManyToOne,
            relationEntityClass: $attributeInstance->entityClass,
            cascade: $attributeInstance->cascade,
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
            columnType: Type::Int,
            relationType: RelationEnum::OneToMany,
            relationEntityClass: $attributeInstance->entityClass,
            relationColumnName: $attributeInstance->relationColumnName ?? CaseUtils::toCase(
                $columnCase,
                NameUtils::getRelationColumnName($this->reflectionClass),
            ),
            cascade: $attributeInstance->cascade,
        );
    }

    /** @param ReflectionAttribute<OneToOne> $attribute */
    private function createFromOneToOneAttribute(
        ReflectionAttribute $attribute,
        ReflectionProperty $reflectionProperty,
        CaseEnum $columnCase,
    ): ColumnSchema
    {
        $attributeInstance = $attribute->newInstance();

        if ($attributeInstance->mappedBy !== null) {
            return new ColumnSchema(
                propertyName: $reflectionProperty->getName(),
                propertyType: PropertyTypeEnum::Relation,
                columnName: CaseUtils::toCase($columnCase, $reflectionProperty->getName()),
                columnType: Type::Int,
                relationType: RelationEnum::OneToOneInverse,
                relationEntityClass: $attributeInstance->entityClass,
                mappedBy: $attributeInstance->mappedBy,
                cascade: $attributeInstance->cascade,
            );
        }

        return new ColumnSchema(
            propertyName: $reflectionProperty->getName(),
            propertyType: PropertyTypeEnum::Relation,
            columnName: $attributeInstance->name ?? CaseUtils::toCase($columnCase, $reflectionProperty->getName() . 'Id'),
            columnType: Type::Int,
            isNullable: $attributeInstance->nullable,
            size: 11,
            relationType: RelationEnum::OneToOne,
            relationEntityClass: $attributeInstance->entityClass,
            cascade: $attributeInstance->cascade,
        );
    }

    /** @param ReflectionAttribute<ManyToMany> $attribute */
    private function createFromManyToManyAttribute(
        ReflectionAttribute $attribute,
        ReflectionProperty $reflectionProperty,
        CaseEnum $columnCase,
    ): ColumnSchema
    {
        $attributeInstance = $attribute->newInstance();

        if ($attributeInstance->mappedBy !== null) {
            return new ColumnSchema(
                propertyName: $reflectionProperty->getName(),
                propertyType: PropertyTypeEnum::Relation,
                columnName: CaseUtils::toCase($columnCase, $reflectionProperty->getName()),
                columnType: Type::Int,
                relationType: RelationEnum::ManyToManyInverse,
                relationEntityClass: $attributeInstance->entityClass,
                mappedBy: $attributeInstance->mappedBy,
                cascade: $attributeInstance->cascade,
            );
        }

        return new ColumnSchema(
            propertyName: $reflectionProperty->getName(),
            propertyType: PropertyTypeEnum::Relation,
            columnName: CaseUtils::toCase($columnCase, $reflectionProperty->getName()),
            columnType: Type::Int,
            relationType: RelationEnum::ManyToMany,
            relationEntityClass: $attributeInstance->entityClass,
            joinTable: $attributeInstance->joinTable ?? throw new \RuntimeException(
                sprintf('ManyToMany attribute on "%s" requires joinTable', $reflectionProperty->getName()),
            ),
            joinColumn: $attributeInstance->joinColumn ?? CaseUtils::toCase(
                $columnCase,
                NameUtils::getRelationColumnName($this->reflectionClass),
            ),
            inverseJoinColumn: $attributeInstance->inverseJoinColumn ?? CaseUtils::toCase(
                $columnCase,
                NameUtils::getRelationColumnName($attributeInstance->entityClass),
            ),
            cascade: $attributeInstance->cascade,
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
