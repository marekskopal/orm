<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Schema\Compiler;

use Closure;
use FilesystemIterator;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToMany;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Attribute\OneToOne;
use MarekSkopal\ORM\Database\AbstractDatabase;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Database\SqliteDatabase;
use MarekSkopal\ORM\Entity\IdentityMap;
use MarekSkopal\ORM\Mapper\Collection;
use MarekSkopal\ORM\Mapper\ExtensionMapperProvider;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Query\Factory\DeleteFactory;
use MarekSkopal\ORM\Query\Factory\InsertFactory;
use MarekSkopal\ORM\Query\Factory\SelectFactory;
use MarekSkopal\ORM\Query\Factory\UpdateFactory;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Query\Where\WhereBuilder;
use MarekSkopal\ORM\Relation\RelationResolver;
use MarekSkopal\ORM\Repository\AbstractRepository;
use MarekSkopal\ORM\Schema\Builder\ClassScanner\ClassScanner;
use MarekSkopal\ORM\Schema\Builder\ColumnSchemaFactory;
use MarekSkopal\ORM\Schema\Builder\EntitySchemaFactory;
use MarekSkopal\ORM\Schema\Builder\SchemaBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Compiler\CodeExporter;
use MarekSkopal\ORM\Schema\Compiler\ExtractorGenerator;
use MarekSkopal\ORM\Schema\Compiler\HydratorGenerator;
use MarekSkopal\ORM\Schema\Compiler\SchemaCompiler;
use MarekSkopal\ORM\Schema\Compiler\SchemaDumper;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\TypesFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Transaction\TransactionProvider;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use MarekSkopal\ORM\Utils\QuoteUtils;
use MarekSkopal\ORM\Utils\ValidationUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

#[CoversClass(SchemaDumper::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(Schema::class)]
#[UsesClass(HydratorGenerator::class)]
#[UsesClass(ExtractorGenerator::class)]
#[UsesClass(SchemaCompiler::class)]
#[UsesClass(CodeExporter::class)]
#[UsesClass(SchemaProvider::class)]
#[UsesClass(ExtensionMapperProvider::class)]
#[UsesClass(RelationResolver::class)]
#[UsesClass(IdentityMap::class)]
#[UsesClass(Collection::class)]
#[UsesClass(ORM::class)]
#[UsesClass(QueryProvider::class)]
#[UsesClass(Select::class)]
#[UsesClass(SelectFactory::class)]
#[UsesClass(InsertFactory::class)]
#[UsesClass(UpdateFactory::class)]
#[UsesClass(DeleteFactory::class)]
#[UsesClass(WhereBuilder::class)]
#[UsesClass(AbstractRepository::class)]
#[UsesClass(TransactionProvider::class)]
#[UsesClass(AbstractDatabase::class)]
#[UsesClass(SqliteDatabase::class)]
#[UsesClass(Column::class)]
#[UsesClass(ColumnEnum::class)]
#[UsesClass(Entity::class)]
#[UsesClass(ManyToMany::class)]
#[UsesClass(ManyToOne::class)]
#[UsesClass(OneToMany::class)]
#[UsesClass(OneToOne::class)]
#[UsesClass(ClassScanner::class)]
#[UsesClass(ColumnSchemaFactory::class)]
#[UsesClass(EntitySchemaFactory::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(PropertyTypeEnum::class)]
#[UsesClass(CaseUtils::class)]
#[UsesClass(NameUtils::class)]
#[UsesClass(QuoteUtils::class)]
#[UsesClass(ValidationUtils::class)]
final class SchemaDumperTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/orm-schema-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->directory);
    }

    public function testDumpedSchemaMatchesBuiltSchema(): void
    {
        $path = $this->directory . '/nested/schema.php';
        $built = $this->builder()->dump($path);
        $loaded = Schema::fromFile($path);

        self::assertSame(array_keys($built->entities), array_keys($loaded->entities));
        foreach ($built->entities as $entityClass => $entitySchema) {
            $loadedSchema = $loaded->entities[$entityClass];
            self::assertSame($entitySchema->table, $loadedSchema->table);
            self::assertSame($entitySchema->tableAlias, $loadedSchema->tableAlias);
            self::assertSame($entitySchema->repositoryClass, $loadedSchema->repositoryClass);
            self::assertEquals($entitySchema->columns, $loadedSchema->columns);
            self::assertInstanceOf(Closure::class, $loadedSchema->hydrator);
            self::assertInstanceOf(Closure::class, $loadedSchema->extractor);
        }

        // The temporary file was renamed into place, not left behind.
        self::assertSame([], glob($this->directory . '/nested/*.tmp'));
    }

    public function testDumpedHydratorAndExtractorBehaveLikeCompiledOnes(): void
    {
        $path = $this->directory . '/schema.php';
        $built = $this->builder()->dump($path);
        $loaded = Schema::fromFile($path);

        $row = [
            'id' => 7,
            'note' => null,
            'name' => 'Widget',
            'count' => 3,
            'ratio' => 1.5,
            'enabled' => 1,
            'uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            'created_at' => '2024-01-01 00:00:00',
            'day' => '2024-02-03',
            'type' => 'admin',
            'level' => 1,
            'price' => 1.0,
            'secret' => 'hidden',
        ];

        $builtProvider = new SchemaProvider($built);
        $loadedProvider = new SchemaProvider($loaded);
        $database = $this::createStub(DatabaseInterface::class);

        $compiled = $builtProvider->getHydrator(TypesFixture::class)($row, new RelationResolver(
            $database,
            $builtProvider,
            new IdentityMap(),
        ));
        $dumped = $loadedProvider->getHydrator(TypesFixture::class)($row, new RelationResolver(
            $database,
            $loadedProvider,
            new IdentityMap(),
        ));

        self::assertEquals($compiled, $dumped);
        self::assertSame($builtProvider->extract($compiled), $loadedProvider->extract($dumped));
    }

    public function testOrmRunsOnDumpedSchema(): void
    {
        $path = $this->directory . '/schema.php';
        $this->builder()->dump($path);

        $database = new SqliteDatabase(':memory:');
        $sql = file_get_contents(__DIR__ . '/../../Fixtures/Database/database_users.sql');
        self::assertIsString($sql);
        foreach (array_filter(
            array_map('trim', explode(';', $sql)),
            static fn(string $statement): bool => $statement !== '',
        ) as $statement) {
            $database->getPdo()->exec($statement);
        }

        $repository = new ORM($database, Schema::fromFile($path))->getRepository(UserFixture::class);
        self::assertSame([1, 2], array_map(static fn(UserFixture $user): int => $user->id, $repository->findAll()));

        $user = UserFixture::create(firstName: 'Bob');
        $repository->persist($user);
        self::assertSame('Bob', $repository->findOne(['id' => $user->id])?->firstName);
    }

    public function testFromFileRejectsMissingFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Schema::fromFile($this->directory . '/missing.php');
    }

    public function testFromFileRejectsFileWithoutSchema(): void
    {
        mkdir($this->directory);
        $path = $this->directory . '/not-a-schema.php';
        file_put_contents($path, "<?php\n\nreturn [];\n");

        $this->expectException(\UnexpectedValueException::class);

        Schema::fromFile($path);
    }

    private function builder(): SchemaBuilder
    {
        return new SchemaBuilder()->addEntityPath(__DIR__ . '/../../Fixtures/Entity');
    }
}
