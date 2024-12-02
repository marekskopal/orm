<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Schema;

use MarekSkopal\ORM\Schema\Schema;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;

class SchemaFixture
{
    public static function create(): Schema
    {
        return new Schema(
            [
                UserFixture::class => UserEntitySchemaFixture::create(),
            ],
        );
    }
}
