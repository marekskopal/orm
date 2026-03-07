<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Schema\Provider;

use InvalidArgumentException;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\SchemaFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaProvider::class)]
#[UsesClass(Schema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(ColumnSchema::class)]
final class SchemaProviderTest extends TestCase
{
    public function testGetEntitySchema(): void
    {
        $schemaProvider = new SchemaProvider(SchemaFixture::create());

        $entitySchema = $schemaProvider->getEntitySchema(UserFixture::class);

        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(EntitySchema::class, $entitySchema);
        self::assertSame(UserFixture::class, $entitySchema->entityClass);
    }

    public function testGetEntitySchemaThrowsForUnknownClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $schemaProvider = new SchemaProvider(new Schema([]));
        $schemaProvider->getEntitySchema(UserFixture::class);
    }

    public function testGetPrimaryColumnSchema(): void
    {
        $schemaProvider = new SchemaProvider(SchemaFixture::create());

        $primaryColumn = $schemaProvider->getPrimaryColumnSchema(UserFixture::class);

        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(ColumnSchema::class, $primaryColumn);
        self::assertTrue($primaryColumn->isPrimary);
        self::assertSame('id', $primaryColumn->propertyName);
    }
}
