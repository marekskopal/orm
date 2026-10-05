<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Compiler;

use Closure;
use MarekSkopal\ORM\Mapper\ExtensionMapperProvider;
use MarekSkopal\ORM\Relation\RelationResolver;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;

/**
 * Compiles generated hydrators and extractors in-process. The source is produced from the schema
 * only (class names, property names and exported literals), never from user data.
 */
final class SchemaCompiler
{
    private readonly HydratorGenerator $hydratorGenerator;

    private readonly ExtractorGenerator $extractorGenerator;

    public function __construct(private readonly SchemaProvider $schemaProvider)
    {
        $this->hydratorGenerator = new HydratorGenerator();
        $this->extractorGenerator = new ExtractorGenerator($schemaProvider);
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return Closure(array<string, mixed>, RelationResolver): T
     */
    public function compileHydrator(string $entityClass): Closure
    {
        /** @var Closure(array<string, mixed>, RelationResolver): T $closure */
        $closure = $this->evaluate($this->hydratorGenerator->generate($this->schemaProvider->getEntitySchema($entityClass)));

        return $closure;
    }

    /**
     * @param class-string $entityClass
     * @return Closure(object, ExtensionMapperProvider): array<string, string|int|float|null>
     */
    public function compileExtractor(string $entityClass): Closure
    {
        /** @var Closure(object, ExtensionMapperProvider): array<string, string|int|float|null> $closure */
        $closure = $this->evaluate($this->extractorGenerator->generate($this->schemaProvider->getEntitySchema($entityClass)));

        return $closure;
    }

    private function evaluate(string $expression): Closure
    {
        // phpcs:ignore Squiz.PHP.Eval.Discouraged
        $closure = eval('declare(strict_types=1); return ' . $expression . ';');
        if (!$closure instanceof Closure) {
            throw new \LogicException('Generated code did not produce a closure.');
        }

        return $closure;
    }
}
