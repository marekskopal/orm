<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Query\Insert;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\EntitySchemaFixture;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Insert::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
final class InsertTest extends TestCase
{
    public function testGetSql(): void
    {
        $pdo = $this->createMock(PDO::class);
        $entitySchema = EntitySchemaFixture::create();
        $mapper = $this->createMock(Mapper::class);

        $insert = new Insert($pdo, $entitySchema, $mapper);
        $insert->entity(UserFixture::create());

        self::assertSame(
            'INSERT INTO users (created_at,first_name,middle_name,last_name,email,is_active) VALUES (:created_at,:first_name,:middle_name,:last_name,:email,:is_active)',
            $insert->getSql(),
        );
    }

    public function testGetSqlNoEntities(): void
    {
        $this->expectException(\LogicException::class);

        $pdo = $this->createMock(PDO::class);
        $entitySchema = EntitySchemaFixture::create();
        $mapper = $this->createMock(Mapper::class);

        $insert = new Insert($pdo, $entitySchema, $mapper);

        $insert->getSql();
    }
}
