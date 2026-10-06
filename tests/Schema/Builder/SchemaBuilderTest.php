<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Schema\Builder;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ForeignKey;
use MarekSkopal\ORM\Attribute\ManyToMany;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Attribute\OneToOne;
use MarekSkopal\ORM\Schema\Builder\ClassScanner\ClassScanner;
use MarekSkopal\ORM\Schema\Builder\ColumnSchemaFactory;
use MarekSkopal\ORM\Schema\Builder\EntitySchemaFactory;
use MarekSkopal\ORM\Schema\Builder\SchemaBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Code;
use MarekSkopal\ORM\Tests\Fixtures\Entity\TagFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaBuilder::class)]
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
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(PropertyTypeEnum::class)]
#[UsesClass(SchemaProvider::class)]
#[UsesClass(Schema::class)]
#[UsesClass(CaseUtils::class)]
#[UsesClass(NameUtils::class)]
final class SchemaBuilderTest extends TestCase
{
    private const string EntityPath = __DIR__ . '/../../Fixtures/Entity';

    public function testTableAliasesFollowSortedFileOrder(): void
    {
        // Filesystems list directories in different orders (sorted on macOS, not on Linux); the
        // scan is sorted by path, so these aliases are the same on every system.
        $schema = new SchemaBuilder()->addEntityPath(self::EntityPath)->build();

        self::assertSame('co', $schema->entities[Code::class]->tableAlias);
        self::assertSame('t', $schema->entities[TagFixture::class]->tableAlias);
        self::assertSame('u', $schema->entities[UserFixture::class]->tableAlias);
        self::assertSame('us', $schema->entities[UserWithAddressFixture::class]->tableAlias);
    }

    public function testOverlappingEntityPathsInAnyOrderBuildTheSameSchema(): void
    {
        $single = new SchemaBuilder()->addEntityPath(self::EntityPath)->build();
        $overlapping = new SchemaBuilder()
            ->addEntityPath(self::EntityPath . '/Enum')
            ->addEntityPath(self::EntityPath)
            ->addEntityPath(self::EntityPath)
            ->build();

        self::assertSame(array_keys($single->entities), array_keys($overlapping->entities));
        foreach ($single->entities as $entityClass => $entitySchema) {
            self::assertSame($entitySchema->tableAlias, $overlapping->entities[$entityClass]->tableAlias);
        }
    }
}
