<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Compiler;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Mapper\ExtensionMapperProvider;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Enum\RelationEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

/**
 * Emits the source of an extractor closure: it reads an entity's insertable properties and returns
 * their database values keyed by column name, in the order of EntitySchema::$insertableColumns.
 */
final class ExtractorGenerator
{
    public function __construct(private readonly SchemaProvider $schemaProvider)
    {
    }

    public function generate(EntitySchema $entitySchema): string
    {
        $entityClass = CodeExporter::className($entitySchema->entityClass);

        $items = [];
        foreach ($entitySchema->getInsertableColumns() as $columnSchema) {
            $items[] = CodeExporter::value($columnSchema->columnName) . ' => ' . $this->valueExpression($entitySchema, $columnSchema) . ',';
        }

        $body = $items === [] ? 'return [];' : "return [\n        " . implode("\n        ", $items) . "\n    ];";

        return '\\Closure::bind(static function (' . $entityClass . ' $e, ' . CodeExporter::className(ExtensionMapperProvider::class)
            . " \$x): array {\n    " . $body . "\n}, null, " . $entityClass . '::class)';
    }

    private function valueExpression(EntitySchema $entitySchema, ColumnSchema $columnSchema): string
    {
        $source = '$e->' . $columnSchema->propertyName;
        if ($columnSchema->isNullable && $this->isWrittenAsIs($columnSchema)) {
            return $source;
        }

        if ($columnSchema->isNullable) {
            return '($v = ' . $source . ') === null ? null : ' . $this->convert($entitySchema, $columnSchema, '$v');
        }

        return $this->convert(
            $entitySchema,
            $columnSchema,
            '(' . $source . ' ?? throw new \\RuntimeException(' . CodeExporter::value(
                sprintf('Column "%s" is not nullable', $columnSchema->columnName),
            ) . '))',
        );
    }

    /** Strings and numbers are bound as they are, so a nullable one needs no null branch. */
    private function isWrittenAsIs(ColumnSchema $columnSchema): bool
    {
        return $columnSchema->relationType === null && in_array(
            $columnSchema->propertyType,
            [PropertyTypeEnum::String, PropertyTypeEnum::Int, PropertyTypeEnum::Float],
            true,
        );
    }

    private function convert(EntitySchema $entitySchema, ColumnSchema $columnSchema, string $source): string
    {
        if ($columnSchema->relationType === RelationEnum::ManyToOne || $columnSchema->relationType === RelationEnum::OneToOne) {
            $relationEntityClass = $columnSchema->relationEntityClass ?? throw new \LogicException(
                sprintf('Relation "%s" has no entity class.', $columnSchema->propertyName),
            );

            return $source . '->' . $this->schemaProvider->getPrimaryColumnSchema($relationEntityClass)->propertyName;
        }

        return match ($columnSchema->propertyType) {
            PropertyTypeEnum::String, PropertyTypeEnum::Int, PropertyTypeEnum::Float => $source,
            PropertyTypeEnum::Bool => '(' . $source . ' ? 1 : 0)',
            PropertyTypeEnum::Uuid => '(string) ' . $source,
            PropertyTypeEnum::DateTime, PropertyTypeEnum::DateTimeImmutable => $source . '->format(' . CodeExporter::value(
                match ($columnSchema->columnType) {
                    Type::Date => 'Y-m-d',
                    Type::Time => 'H:i:s',
                    default => 'Y-m-d H:i:s',
                },
            ) . ')',
            PropertyTypeEnum::Enum => $source . '->value',
            PropertyTypeEnum::Extension => '$x->mapToColumn(' . CodeExporter::className($entitySchema->entityClass) . '::class, '
                . CodeExporter::value($columnSchema->propertyName) . ', ' . $source . ')',
            PropertyTypeEnum::Relation => throw new \LogicException(
                sprintf('Relation "%s" cannot be written as a column.', $columnSchema->propertyName),
            ),
        };
    }
}
