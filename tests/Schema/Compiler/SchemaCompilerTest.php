<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Schema\Compiler;

use DateTime;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ForeignKey;
use MarekSkopal\ORM\Attribute\ManyToMany;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Attribute\OneToOne;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Entity\IdentityMap;
use MarekSkopal\ORM\Mapper\Collection;
use MarekSkopal\ORM\Mapper\ExtensionMapperProvider;
use MarekSkopal\ORM\Relation\RelationResolver;
use MarekSkopal\ORM\Schema\Builder\ClassScanner\ClassScanner;
use MarekSkopal\ORM\Schema\Builder\ColumnSchemaFactory;
use MarekSkopal\ORM\Schema\Builder\EntitySchemaFactory;
use MarekSkopal\ORM\Schema\Builder\SchemaBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\Compiler\CodeExporter;
use MarekSkopal\ORM\Schema\Compiler\ExtractorGenerator;
use MarekSkopal\ORM\Schema\Compiler\HydratorGenerator;
use MarekSkopal\ORM\Schema\Compiler\NormalizerGenerator;
use MarekSkopal\ORM\Schema\Compiler\SchemaCompiler;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\AddressWithUsersFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserLevelEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\TypesFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use MarekSkopal\ORM\Utils\ValidationUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(SchemaCompiler::class)]
#[CoversClass(SchemaProvider::class)]
#[CoversClass(ExtensionMapperProvider::class)]
#[UsesClass(HydratorGenerator::class)]
#[UsesClass(ExtractorGenerator::class)]
#[UsesClass(CodeExporter::class)]
#[UsesClass(RelationResolver::class)]
#[UsesClass(IdentityMap::class)]
#[UsesClass(Collection::class)]
#[UsesClass(Column::class)]
#[UsesClass(ColumnEnum::class)]
#[UsesClass(Entity::class)]
#[UsesClass(ManyToOne::class)]
#[UsesClass(OneToMany::class)]
#[UsesClass(ClassScanner::class)]
#[UsesClass(ColumnSchemaFactory::class)]
#[UsesClass(EntitySchemaFactory::class)]
#[UsesClass(SchemaBuilder::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(PropertyTypeEnum::class)]
#[UsesClass(Schema::class)]
#[UsesClass(CaseUtils::class)]
#[UsesClass(NameUtils::class)]
#[UsesClass(ValidationUtils::class)]
#[UsesClass(ForeignKey::class)]
#[UsesClass(ManyToMany::class)]
#[UsesClass(OneToOne::class)]
#[UsesClass(NormalizerGenerator::class)]
final class SchemaCompilerTest extends TestCase
{
    private const string Uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    private SchemaProvider $schemaProvider;

    private RelationResolver $relationResolver;

    protected function setUp(): void
    {
        $this->schemaProvider = new SchemaProvider(new SchemaBuilder()->addEntityPath(__DIR__ . '/../../Fixtures/Entity')->build());
        $this->relationResolver = new RelationResolver(
            $this::createStub(DatabaseInterface::class),
            $this->schemaProvider,
            new IdentityMap(),
        );
    }

    public function testHydratesEveryColumnType(): void
    {
        $entity = $this->hydrateTypes([
            'id' => '7',
            'note' => 'hello',
            'name' => 'Widget',
            'count' => '3',
            'ratio' => '1.5',
            'enabled' => true,
            'uuid' => self::Uuid,
            'created_at' => 1704067200,
            'day' => '2024-02-03',
            'type' => 'admin',
            'level' => '2',
            'price' => 1.0,
            'secret' => 'hidden',
        ]);

        self::assertSame(7, $entity->id);
        self::assertSame('hello', $entity->note);
        self::assertSame('Widget', $entity->name);
        self::assertSame(3, $entity->count);
        self::assertSame(1.5, $entity->ratio);
        self::assertTrue($entity->enabled);
        self::assertSame(self::Uuid, $entity->uuid->toString());
        self::assertSame(DateTime::class, $entity->createdAt::class);
        self::assertSame('2024-01-01 00:00:00', $entity->createdAt->format('Y-m-d H:i:s'));
        self::assertSame('2024-02-03', $entity->day->format('Y-m-d'));
        self::assertSame(UserTypeEnum::Admin, $entity->type);
        // An int-backed enum accepts the driver's string representation.
        self::assertSame(UserLevelEnum::Premium, $entity->level);
        // The extension mapper adds 1.
        self::assertSame(2.0, $entity->price);
        // Private readonly properties are written through the bound scope.
        self::assertSame('hidden', $entity->getSecret());
    }

    public function testHydratesNullsAndStringDates(): void
    {
        $entity = $this->hydrateTypes(
            $this->typesRow(['note' => null, 'level' => null, 'created_at' => '2024-05-06 07:08:09', 'enabled' => 0]),
        );

        self::assertNull($entity->note);
        self::assertNull($entity->level);
        self::assertFalse($entity->enabled);
        self::assertSame('2024-05-06 07:08:09', $entity->createdAt->format('Y-m-d H:i:s'));
    }

    public function testNonNullableColumnRejectsNull(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Column "name" is not nullable');

        $this->hydrateTypes($this->typesRow(['name' => null]));
    }

    public function testExtractsDatabaseValues(): void
    {
        $entity = $this->hydrateTypes($this->typesRow([]));

        self::assertSame(
            [
                'note' => null,
                'name' => 'Widget',
                'count' => 3,
                'ratio' => 1.5,
                'enabled' => 1,
                'uuid' => self::Uuid,
                'created_at' => '2024-01-01 00:00:00',
                'day' => '2024-02-03',
                'type' => 'admin',
                'level' => 2,
                // Hydration adds 1, extraction adds 1 again.
                'price' => 3.0,
                'secret' => 'hidden',
            ],
            $this->schemaProvider->extract($entity),
        );
    }

    public function testExtractsRelationsAsForeignKeysWithoutLoadingThem(): void
    {
        $hydrator = $this->schemaProvider->getHydrator(UserWithAddressFixture::class);
        $user = $hydrator([
            'id' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'first_name' => 'John',
            'middle_name' => null,
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'is_active' => 1,
            'type' => 'user',
            'address_id' => 4,
            'second_address_id' => null,
        ], $this->relationResolver);

        $values = $this->schemaProvider->extract($user);

        self::assertSame(4, $values['address_id']);
        self::assertNull($values['second_address_id']);
        self::assertTrue(new ReflectionClass(AddressWithUsersFixture::class)->isUninitializedLazyObject($user->address));
    }

    public function testHydratorAndExtractorAreCompiledOnce(): void
    {
        self::assertSame(
            $this->schemaProvider->getHydrator(TypesFixture::class),
            $this->schemaProvider->getHydrator(TypesFixture::class),
        );
        self::assertSame(
            $this->schemaProvider->getExtractor(TypesFixture::class),
            $this->schemaProvider->getExtractor(TypesFixture::class),
        );
    }

    public function testExtensionMapperProviderRequiresSchemaProvider(): void
    {
        $this->expectException(\LogicException::class);

        new ExtensionMapperProvider()->mapToProperty(TypesFixture::class, 'price', 1.0);
    }

    /** @param array<string, mixed> $row */
    private function hydrateTypes(array $row): TypesFixture
    {
        return $this->schemaProvider->getHydrator(TypesFixture::class)($row, $this->relationResolver);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function typesRow(array $overrides): array
    {
        return array_replace([
            'id' => 7,
            'note' => null,
            'name' => 'Widget',
            'count' => 3,
            'ratio' => 1.5,
            'enabled' => 1,
            'uuid' => self::Uuid,
            'created_at' => '2024-01-01 00:00:00',
            'day' => '2024-02-03',
            'type' => 'admin',
            'level' => 2,
            'price' => 1.0,
            'secret' => 'hidden',
        ], $overrides);
    }
}
