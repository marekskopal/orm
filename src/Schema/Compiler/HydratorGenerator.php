<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Compiler;

use DateTime;
use DateTimeImmutable;
use MarekSkopal\ORM\Relation\RelationResolver;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionEnum;

/**
 * Emits the source of a hydrator closure: straight-line code that turns one database row into an
 * entity. Types, enum backing types and relation kinds are resolved here, once, instead of per row.
 * The closure is bound into the entity's scope so private and readonly properties can be written.
 */
final class HydratorGenerator
{
    public function __construct(private readonly SchemaProvider $schemaProvider)
    {
    }

    public function generate(EntitySchema $entitySchema): string
    {
        $entityClass = CodeExporter::className($entitySchema->entityClass);
        $reflectionClass = new ReflectionClass($entitySchema->entityClass);
        $primaryColumn = $entitySchema->getPrimaryColumn();

        $lines = [];
        foreach ($entitySchema->columns as $columnSchema) {
            if ($this->isKeyedByOwnerId($columnSchema)) {
                // Collection and inverse relations are keyed by this entity's id.
                $lines[] = '$id = $row[' . CodeExporter::value($primaryColumn->columnName) . '];';
                break;
            }
        }

        foreach ($entitySchema->columns as $propertyName => $columnSchema) {
            $lines[] = '$p_' . $propertyName . ' = ' . $this->valueExpression($entitySchema, $columnSchema, $reflectionClass) . ';';
        }

        $constructorArguments = [];
        $constructorProperties = [];
        foreach ($reflectionClass->getConstructor()?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();
            if (isset($entitySchema->columns[$name])) {
                $constructorArguments[] = $name . ': $p_' . $name;
                $constructorProperties[$name] = true;
                continue;
            }

            if (!$parameter->isOptional()) {
                throw new \LogicException(sprintf(
                    'Constructor parameter "%s" of entity "%s" has no mapped property of the same name.',
                    $name,
                    $entitySchema->entityClass,
                ));
            }
        }

        $lines[] = '$e = new ' . $entityClass . '(' . implode(', ', $constructorArguments) . ');';
        foreach (array_keys($entitySchema->columns) as $propertyName) {
            if (!isset($constructorProperties[$propertyName])) {
                $lines[] = '$e->' . $propertyName . ' = $p_' . $propertyName . ';';
            }
        }

        // The raw values of the updatable columns, for change detection on flush.
        $snapshot = [];
        foreach ($entitySchema->getUpdatableColumns() as $columnSchema) {
            $snapshot[] = '$row[' . CodeExporter::value($columnSchema->columnName) . '] ?? null';
        }
        $lines[] = '$r->snapshots[\\spl_object_id($e)] = [' . implode(', ', $snapshot) . '];';
        $lines[] = 'return $e;';

        return '\\Closure::bind(static function (array $row, ' . CodeExporter::className(RelationResolver::class) . ' $r): '
            . $entityClass . " {\n    " . implode("\n    ", $lines) . "\n}, null, " . $entityClass . '::class)';
    }

    private function isKeyedByOwnerId(ColumnSchema $columnSchema): bool
    {
        return in_array(
            $columnSchema->relationType,
            [RelationEnum::OneToMany, RelationEnum::ManyToMany, RelationEnum::ManyToManyInverse, RelationEnum::OneToOneInverse],
            true,
        );
    }

    /** @param ReflectionClass<object> $reflectionClass */
    private function valueExpression(EntitySchema $entitySchema, ColumnSchema $columnSchema, ReflectionClass $reflectionClass): string
    {
        // A literal key, so no string is built per row.
        $relationKey = CodeExporter::value($entitySchema->entityClass . '::' . $columnSchema->propertyName);

        // Collection and inverse relations have no column of their own; they are keyed by this entity's id.
        switch ($columnSchema->relationType) {
            case RelationEnum::OneToMany:
            case RelationEnum::ManyToMany:
            case RelationEnum::ManyToManyInverse:
                return '$r->collection(' . $relationKey . ', $id)';
            case RelationEnum::OneToOneInverse:
                $nullable = $reflectionClass->getProperty($columnSchema->propertyName)->getType()?->allowsNull() ?? true;
                return '$r->oneToOneInverse(' . $relationKey . ', $id, ' . CodeExporter::value($nullable) . ')';
            default:
                break;
        }

        $source = '$row[' . CodeExporter::value($columnSchema->columnName) . ']';
        if ($columnSchema->isNullable) {
            return '($v = ' . $source . ' ?? null) === null ? null : ' . $this->convert($entitySchema, $columnSchema, '$v');
        }

        return $this->convert(
            $entitySchema,
            $columnSchema,
            '(' . $source . ' ?? throw new \\RuntimeException(' . CodeExporter::value(
                sprintf('Column "%s" is not nullable', $columnSchema->columnName),
            ) . '))',
        );
    }

    /**
     * Converts the raw database value in $source to the property value; $source is evaluated exactly
     * once. A relation column converts to the related entity (through $r) unless $relationAsKey, in
     * which case it converts to the related primary key value.
     */
    public function convert(
        EntitySchema $entitySchema,
        ColumnSchema $columnSchema,
        string $source,
        string $extensionCall = '$r->mapExtension',
        bool $relationAsKey = false,
    ): string
    {
        if ($columnSchema->relationType === RelationEnum::ManyToOne || $columnSchema->relationType === RelationEnum::OneToOne) {
            $relationEntityClass = $columnSchema->relationEntityClass ?? throw new \LogicException(
                sprintf('Relation "%s" has no entity class.', $columnSchema->propertyName),
            );
            $targetSchema = $this->schemaProvider->getEntitySchema($relationEntityClass);
            $key = $this->convert($targetSchema, $targetSchema->getPrimaryColumn(), $source, $extensionCall);

            return $relationAsKey ? $key : '$r->manyToOne(' . CodeExporter::className($relationEntityClass) . '::class, ' . $key . ')';
        }

        return match ($columnSchema->propertyType) {
            PropertyTypeEnum::String => '(string) ' . $source,
            PropertyTypeEnum::Int => '(int) ' . $source,
            PropertyTypeEnum::Float => '(float) ' . $source,
            PropertyTypeEnum::Bool => '(bool) ' . $source,
            PropertyTypeEnum::Uuid => CodeExporter::className(Uuid::class) . '::fromString((string) ' . $source . ')',
            PropertyTypeEnum::DateTime => $this->convertDateTime(DateTime::class, $source),
            PropertyTypeEnum::DateTimeImmutable => $this->convertDateTime(DateTimeImmutable::class, $source),
            PropertyTypeEnum::Enum => $this->convertEnum($columnSchema, $source),
            PropertyTypeEnum::Extension => $extensionCall . '(' . CodeExporter::className($entitySchema->entityClass) . '::class, '
                . CodeExporter::value($columnSchema->propertyName) . ', ' . $source . ')',
            PropertyTypeEnum::Relation => throw new \LogicException(
                sprintf('Relation "%s" has an unsupported relation type.', $columnSchema->propertyName),
            ),
        };
    }

    /** @param class-string $dateTimeClass */
    private function convertDateTime(string $dateTimeClass, string $source): string
    {
        $className = CodeExporter::className($dateTimeClass);
        $assign = $source === '$v' ? '$v' : '($v = ' . $source . ')';

        // Integer columns hold Unix timestamps (SQLite); everything else is a date string.
        return '(\\is_int(' . $assign . ') ? new ' . $className . "('@' . \$v) : new " . $className . '($v))';
    }

    private function convertEnum(ColumnSchema $columnSchema, string $source): string
    {
        if ($columnSchema->enumClass === null) {
            return 'null';
        }

        // Drivers may return an int column as int or string, and from() is strict about the backing type.
        $backingType = (string) new ReflectionEnum($columnSchema->enumClass)->getBackingType();

        return CodeExporter::className($columnSchema->enumClass) . '::from((' . ($backingType === 'int' ? 'int' : 'string') . ') '
            . $source . ')';
    }
}
