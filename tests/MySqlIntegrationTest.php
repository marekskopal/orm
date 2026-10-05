<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Attribute\OneToMany;
use MarekSkopal\ORM\Database\AbstractDatabase;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Database\MySqlDatabase;
use MarekSkopal\ORM\Entity\EntityCache;
use MarekSkopal\ORM\Entity\EntityFactory;
use MarekSkopal\ORM\Entity\EntityReflection;
use MarekSkopal\ORM\Mapper\Collection;
use MarekSkopal\ORM\Mapper\Mapper;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Query\Delete;
use MarekSkopal\ORM\Query\Factory\DeleteFactory;
use MarekSkopal\ORM\Query\Factory\InsertFactory;
use MarekSkopal\ORM\Query\Factory\SelectFactory;
use MarekSkopal\ORM\Query\Factory\UpdateFactory;
use MarekSkopal\ORM\Query\Insert;
use MarekSkopal\ORM\Query\Model\Join;
use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Query\Update;
use MarekSkopal\ORM\Query\Where\WhereBuilder;
use MarekSkopal\ORM\Repository\AbstractRepository;
use MarekSkopal\ORM\Schema\Builder\ClassScanner\ClassScanner;
use MarekSkopal\ORM\Schema\Builder\ColumnSchemaFactory;
use MarekSkopal\ORM\Schema\Builder\EntitySchemaFactory;
use MarekSkopal\ORM\Schema\Builder\SchemaBuilder;
use MarekSkopal\ORM\Schema\ColumnSchema;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\PropertyTypeEnum;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Utils\CaseUtils;
use MarekSkopal\ORM\Utils\NameUtils;
use MarekSkopal\ORM\Utils\ValidationUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MySqlDatabase::class)]
#[UsesClass(Column::class)]
#[UsesClass(ColumnEnum::class)]
#[UsesClass(Entity::class)]
#[UsesClass(ManyToOne::class)]
#[UsesClass(AbstractDatabase::class)]
#[UsesClass(EntityCache::class)]
#[UsesClass(EntityFactory::class)]
#[UsesClass(EntityReflection::class)]
#[UsesClass(Mapper::class)]
#[UsesClass(ORM::class)]
#[UsesClass(QueryProvider::class)]
#[UsesClass(Select::class)]
#[UsesClass(SelectFactory::class)]
#[UsesClass(InsertFactory::class)]
#[UsesClass(Insert::class)]
#[UsesClass(UpdateFactory::class)]
#[UsesClass(Update::class)]
#[UsesClass(DeleteFactory::class)]
#[UsesClass(Delete::class)]
#[UsesClass(AbstractRepository::class)]
#[UsesClass(ColumnSchemaFactory::class)]
#[UsesClass(EntitySchemaFactory::class)]
#[UsesClass(SchemaBuilder::class)]
#[UsesClass(ColumnSchema::class)]
#[UsesClass(EntitySchema::class)]
#[UsesClass(PropertyTypeEnum::class)]
#[UsesClass(Schema::class)]
#[UsesClass(SchemaProvider::class)]
#[UsesClass(CaseUtils::class)]
#[UsesClass(ClassScanner::class)]
#[UsesClass(NameUtils::class)]
#[UsesClass(ValidationUtils::class)]
#[UsesClass(OneToMany::class)]
#[UsesClass(WhereBuilder::class)]
#[UsesClass(Collection::class)]
#[UsesClass(Join::class)]
#[RequiresPhpExtension('pdo_mysql')]
final class MySqlIntegrationTest extends AbstractDriverIntegrationTestCase
{
    protected function connect(): DatabaseInterface
    {
        // PDO treats host "localhost" as a Unix socket for MySQL, so the default is the TCP address.
        return new MySqlDatabase(
            host: self::env('MYSQL_HOST', '127.0.0.1'),
            username: self::env('MYSQL_USER', 'root'),
            password: self::env('MYSQL_PASSWORD', ''),
            database: self::env('MYSQL_DB', 'orm_test'),
            port: (int) self::env('MYSQL_PORT', '3306'),
        );
    }

    protected function getFixtureSuffix(): string
    {
        return 'mysql';
    }
}
