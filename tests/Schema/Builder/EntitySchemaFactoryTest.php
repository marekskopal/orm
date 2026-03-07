<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Schema\Builder;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Schema\Builder\ColumnSchemaFactory;
use MarekSkopal\ORM\Schema\Builder\EntitySchemaFactory;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\CaseEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(EntitySchemaFactory::class)]
#[UsesClass(ColumnSchemaFactory::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(Column::class)]
#[UsesClass(ColumnEnum::class)]
#[UsesClass(Entity::class)]
#[UsesClass(ManyToOne::class)]
#[UsesClass(OneToMany::class)]
#[UsesClass(CaseUtils::class)]
#[UsesClass(NameUtils::class)]
final class EntitySchemaFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $factory = new EntitySchemaFactory();

        /** @phpstan-ignore-next-line argument.type */
        $entitySchema = $factory->create(new ReflectionClass(UserFixture::class), CaseEnum::SnakeCase, CaseEnum::SnakeCase);

        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(EntitySchema::class, $entitySchema);
        self::assertSame(UserFixture::class, $entitySchema->entityClass);
        self::assertSame('users', $entitySchema->table);
        self::assertArrayHasKey('id', $entitySchema->columns);
        self::assertArrayHasKey('firstName', $entitySchema->columns);
    }

    public function testCreateTableAliasDeduplicated(): void
    {
        $factory = new EntitySchemaFactory();

        /** @phpstan-ignore-next-line argument.type */
        $schema1 = $factory->create(new ReflectionClass(UserFixture::class), CaseEnum::SnakeCase, CaseEnum::SnakeCase);
        /** @phpstan-ignore-next-line argument.type */
        $schema2 = $factory->create(new ReflectionClass(UserWithAddressFixture::class), CaseEnum::SnakeCase, CaseEnum::SnakeCase);

        // Both entities share table prefix 'u', so the second must get a longer alias
        self::assertNotSame($schema1->tableAlias, $schema2->tableAlias);
    }

    public function testCreateUsesTableCaseSnakeCase(): void
    {
        $factory = new EntitySchemaFactory();

        /** @phpstan-ignore-next-line argument.type */
        $entitySchema = $factory->create(new ReflectionClass(UserFixture::class), CaseEnum::SnakeCase, CaseEnum::SnakeCase);

        // UserFixture has explicit table='users' in #[Entity] attribute, so it overrides the case
        self::assertSame('users', $entitySchema->table);
    }
}
