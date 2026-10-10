<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Schema;

use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Tests\Fixtures\Schema\UserEntitySchemaFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntitySchema::class)]
#[UsesClass(ColumnSchema::class)]
final class EntitySchemaTest extends TestCase
{
    public function testColumnLookupsByPropertyAndColumnName(): void
    {
        $entitySchema = UserEntitySchemaFixture::create();

        self::assertSame('first_name', $entitySchema->getColumnByPropertyName('firstName')->columnName);
        self::assertSame('firstName', $entitySchema->getColumnByColumnName('first_name')->propertyName);
        self::assertSame('id', $entitySchema->getPrimaryColumn()->propertyName);
    }

    public function testUnknownPropertyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Column schema for property "first_name" not found.');

        UserEntitySchemaFixture::create()->getColumnByPropertyName('first_name');
    }

    public function testUnknownColumnIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Column schema for column "firstName" not found.');

        UserEntitySchemaFixture::create()->getColumnByColumnName('firstName');
    }
}
