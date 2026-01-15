<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\Query\Update;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use MarekSkopal\ORM\Tests\Fixtures\Schema\EntitySchemaFixture;
use MarekSkopal\ORM\Utils\NameUtils;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Update::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(NameUtils::class)]
final class UpdateTest extends TestCase
{
    public function testGetSql(): void
    {
        $pdo = $this::createStub(PDO::class);
        $entitySchema = EntitySchemaFixture::create();
        $mapper = $this::createStub(Mapper::class);

        $insert = new Update($pdo, UserFixture::class, $entitySchema, $mapper);
        $insert->entity(UserFixture::create());

        self::assertSame(
            'UPDATE `users` SET `created_at`=:createdAt,`first_name`=:firstName,`middle_name`=:middleName,`last_name`=:lastName,`email`=:email,`is_active`=:isActive,`type`=:type WHERE `id`=:id',
            $insert->getSql(),
        );
    }

    public function testGetSqlNoEntities(): void
    {
        $this->expectException(\LogicException::class);

        $pdo = $this::createStub(PDO::class);
        $entitySchema = EntitySchemaFixture::create();
        $mapper = $this::createStub(Mapper::class);

        $insert = new Update($pdo, UserFixture::class, $entitySchema, $mapper);

        $insert->getSql();
    }
}
