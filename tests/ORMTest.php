<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests;

use MarekSkopal\ORM\Database\AbstractDatabase;
use MarekSkopal\ORM\Database\SqliteDatabase;
use MarekSkopal\ORM\Mapper\ExtensionMapperProvider;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Query\Factory\DeleteFactory;
use MarekSkopal\ORM\Query\Factory\InsertFactory;
use MarekSkopal\ORM\Query\Factory\SelectFactory;
use MarekSkopal\ORM\Query\Factory\UpdateFactory;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Relation\RelationResolver;
use MarekSkopal\ORM\Repository\AbstractRepository;
use MarekSkopal\ORM\Repository\RepositoryInterface;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Compiler\CodeExporter;
use MarekSkopal\ORM\Schema\Compiler\ExtractorGenerator;
use MarekSkopal\ORM\Schema\Compiler\HydratorGenerator;
use MarekSkopal\ORM\Schema\Compiler\SchemaCompiler;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\SchemaFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ORM::class)]
#[UsesClass(AbstractDatabase::class)]
#[UsesClass(SqliteDatabase::class)]
#[UsesClass(QueryProvider::class)]
#[UsesClass(SelectFactory::class)]
#[UsesClass(InsertFactory::class)]
#[UsesClass(UpdateFactory::class)]
#[UsesClass(DeleteFactory::class)]
#[UsesClass(AbstractRepository::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(Schema::class)]
#[UsesClass(SchemaProvider::class)]
#[UsesClass(RelationResolver::class)]
#[UsesClass(SchemaCompiler::class)]
#[UsesClass(HydratorGenerator::class)]
#[UsesClass(ExtractorGenerator::class)]
#[UsesClass(CodeExporter::class)]
#[UsesClass(ExtensionMapperProvider::class)]
final class ORMTest extends TestCase
{
    public function testGetRepository(): void
    {
        $database = new SqliteDatabase(':memory:');

        $orm = new ORM($database, SchemaFixture::create());

        $repository = $orm->getRepository(UserFixture::class);

        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(RepositoryInterface::class, $repository);
    }
}
