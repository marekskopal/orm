<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Schema\Compiler;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ForeignKey;
use MarekSkopal\ORM\Attribute\ManyToMany;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Attribute\OneToOne;
use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Repository\Repository;
use MarekSkopal\ORM\Schema\Builder\ClassScanner\ClassScanner;
use MarekSkopal\ORM\Schema\Builder\ColumnSchemaFactory;
use MarekSkopal\ORM\Schema\Builder\EntitySchemaFactory;
use MarekSkopal\ORM\Schema\Builder\SchemaBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Compiler\CodeExporter;
use MarekSkopal\ORM\Schema\Compiler\ExtractorGenerator;
use MarekSkopal\ORM\Schema\Compiler\HydratorGenerator;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Tests\Fixtures\Compiler\UnmappedConstructorFixture;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

/**
 * Golden-file tests for the generated hydrator and extractor source of every fixture entity.
 * Regenerate the files after an intended change with:
 *
 *     ORM_UPDATE_GOLDEN=1 vendor/bin/phpunit tests/Schema/Compiler/GeneratedCodeTest.php
 */
#[CoversClass(HydratorGenerator::class)]
#[CoversClass(ExtractorGenerator::class)]
#[CoversClass(CodeExporter::class)]
#[UsesClass(Column::class)]
#[UsesClass(ColumnEnum::class)]
#[UsesClass(Entity::class)]
#[UsesClass(ForeignKey::class)]
#[UsesClass(ManyToMany::class)]
#[UsesClass(ManyToOne::class)]
#[UsesClass(OneToMany::class)]
#[UsesClass(OneToOne::class)]
#[UsesClass(ClassScanner::class)]
#[UsesClass(ColumnSchemaFactory::class)]
#[UsesClass(EntitySchemaFactory::class)]
#[UsesClass(SchemaBuilder::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(PropertyTypeEnum::class)]
#[UsesClass(SchemaProvider::class)]
#[UsesClass(Schema::class)]
#[UsesClass(CaseUtils::class)]
#[UsesClass(NameUtils::class)]
final class GeneratedCodeTest extends TestCase
{
    private const string GoldenDirectory = __DIR__ . '/../../Fixtures/Compiler/Golden';

    /** @return iterable<string, array{class-string}> */
    public static function entityProvider(): iterable
    {
        foreach (array_keys(self::buildSchema()->entities) as $entityClass) {
            yield new ReflectionClass($entityClass)->getShortName() => [$entityClass];
        }
    }

    /** @param class-string $entityClass */
    #[DataProvider('entityProvider')]
    public function testHydratorMatchesGoldenFile(string $entityClass): void
    {
        $entitySchema = self::buildSchema()->entities[$entityClass];

        $this->assertGolden($entityClass, 'hydrator', new HydratorGenerator()->generate($entitySchema));
    }

    /** @param class-string $entityClass */
    #[DataProvider('entityProvider')]
    public function testExtractorMatchesGoldenFile(string $entityClass): void
    {
        $schema = self::buildSchema();

        $this->assertGolden(
            $entityClass,
            'extractor',
            new ExtractorGenerator(new SchemaProvider($schema))->generate($schema->entities[$entityClass]),
        );
    }

    public function testRequiredConstructorParameterWithoutColumnIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Constructor parameter "required" of entity "' . UnmappedConstructorFixture::class . '"');

        new HydratorGenerator()->generate(new EntitySchema(
            entityClass: UnmappedConstructorFixture::class,
            repositoryClass: Repository::class,
            table: 'unmapped',
            tableAlias: 'u',
            columns: [
                'id' => new ColumnSchema('id', PropertyTypeEnum::Int, 'id', Type::Int, isPrimary: true),
                'name' => new ColumnSchema('name', PropertyTypeEnum::String, 'name', Type::String),
            ],
        ));
    }

    public function testExportsValues(): void
    {
        self::assertSame('null', CodeExporter::value(null));
        self::assertSame('true', CodeExporter::value(true));
        self::assertSame("'it\\'s'", CodeExporter::value("it's"));
        self::assertSame('1.5', CodeExporter::value(1.5));
        self::assertSame('\\' . Type::class . '::Int', CodeExporter::value(Type::Int));
        self::assertSame('[1, 2]', CodeExporter::value([1, 2]));
        self::assertSame("['a' => [], 'b' => null]", CodeExporter::value(['a' => [], 'b' => null]));

        $this->expectException(\InvalidArgumentException::class);
        CodeExporter::value(new stdClass());
    }

    private static function buildSchema(): Schema
    {
        return new SchemaBuilder()->addEntityPath(__DIR__ . '/../../Fixtures/Entity')->build();
    }

    /** @param class-string $entityClass */
    private function assertGolden(string $entityClass, string $kind, string $source): void
    {
        $path = self::GoldenDirectory . '/' . new ReflectionClass($entityClass)->getShortName() . '.' . $kind . '.php.txt';

        if (getenv('ORM_UPDATE_GOLDEN') === '1') {
            if (!is_dir(self::GoldenDirectory)) {
                mkdir(self::GoldenDirectory, 0777, true);
            }

            file_put_contents($path, $source . "\n");
        }

        self::assertFileExists($path, 'Golden file missing; run with ORM_UPDATE_GOLDEN=1 to create it.');
        self::assertSame(file_get_contents($path), $source . "\n");
    }
}
