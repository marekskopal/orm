<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Compiler;

use MarekSkopal\ORM\Mapper\ExtensionMapperProvider;
use MarekSkopal\ORM\Schema\EntitySchema;

/**
 * Emits the source of a normalizer closure. It turns a hydration snapshot (the raw values of the
 * updatable columns, in schema order) into exactly what the extractor would produce for an
 * unchanged entity, by applying the hydrator's conversion and then the extractor's. Comparing its
 * output with the extractor's output with !== finds the changed columns.
 *
 * Normalizing happens on flush, for the entities being flushed only, so hydration stays cheap.
 */
final class NormalizerGenerator
{
    public function __construct(
        private readonly HydratorGenerator $hydratorGenerator,
        private readonly ExtractorGenerator $extractorGenerator,
    ) {
    }

    public function generate(EntitySchema $entitySchema): string
    {
        $items = [];
        $index = 0;
        foreach ($entitySchema->getUpdatableColumns() as $columnSchema) {
            $source = '$s[' . $index . ']';
            $propertyValue = $this->hydratorGenerator->convert(
                $entitySchema,
                $columnSchema,
                $source,
                '$x->mapToProperty',
                relationAsKey: true,
            );

            $items[] = CodeExporter::value($columnSchema->columnName) . ' => ' . $source . ' === null ? null : '
                . $this->extractorGenerator->convert($entitySchema, $columnSchema, '(' . $propertyValue . ')', relationAsKey: true) . ',';
            $index++;
        }

        $body = $items === [] ? 'return [];' : "return [\n        " . implode("\n        ", $items) . "\n    ];";

        return 'static function (array $s, ' . CodeExporter::className(ExtensionMapperProvider::class) . " \$x): array {\n    "
            . $body . "\n}";
    }
}
