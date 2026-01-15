<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Query\Insert;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\EntitySchemaFixture;
use MarekSkopal\ORM\Utils\NameUtils;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Insert::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(NameUtils::class)]
final class InsertTest extends TestCase
{
    public function testGetSql(): void
    {
        $pdo = $this::createStub(PDO::class);
        $entitySchema = EntitySchemaFixture::create();
        $mapper = $this::createStub(Mapper::class);

        $insert = new Insert($pdo, UserFixture::class, $entitySchema, $mapper);
        $insert->entity(UserFixture::create());
        $insert->entity(UserFixture::create());

        self::assertSame(
            'INSERT INTO `users` (`created_at`,`first_name`,`middle_name`,`last_name`,`email`,`is_active`,`type`) VALUES (?,?,?,?,?,?,?),(?,?,?,?,?,?,?)',
            $insert->getSql(),
        );
    }

    public function testGetSqlNoEntities(): void
    {
        $this->expectException(\LogicException::class);

        $pdo = $this::createStub(PDO::class);
        $entitySchema = EntitySchemaFixture::create();
        $mapper = $this::createStub(Mapper::class);

        $insert = new Insert($pdo, UserFixture::class, $entitySchema, $mapper);

        $insert->getSql();
    }
}
