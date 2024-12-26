<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Query\Delete;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\EntitySchemaFixture;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Delete::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(ColumnSchema::class)]
class DeleteTest extends TestCase
{
    public function testGetSql(): void
    {
        $pdo = $this->createMock(PDO::class);
        $entitySchema = EntitySchemaFixture::create();
        $primaryColumnSchema = new ColumnSchema(
            propertyName: 'id',
            propertyType: PropertyTypeEnum::Int,
            columnName: 'id',
            columnType: Type::Int,
        );

        $delete = new Delete($pdo, $entitySchema, $primaryColumnSchema);
        $delete->entity(UserFixture::create());

        self::assertSame(
            'DELETE FROM users WHERE id IN (?)',
            $delete->getSql(),
        );
    }
}
