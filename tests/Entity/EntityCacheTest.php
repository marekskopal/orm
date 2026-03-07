<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Entity;

use MarekSkopal\ORM\Entity\EntityCache;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityCache::class)]
final class EntityCacheTest extends TestCase
{
    public function testGetEntityReturnsNullWhenMissing(): void
    {
        $cache = new EntityCache();
        self::assertNull($cache->getEntity(UserFixture::class, 1));
    }

    public function testAddAndGetEntity(): void
    {
        $cache = new EntityCache();
        $user = UserFixture::create();

        $cache->addEntity($user, 1);

        self::assertSame($user, $cache->getEntity(UserFixture::class, 1));
    }

    public function testGetEntityReturnsNullForDifferentId(): void
    {
        $cache = new EntityCache();
        $user = UserFixture::create();

        $cache->addEntity($user, 1);

        self::assertNull($cache->getEntity(UserFixture::class, 2));
    }

    public function testClear(): void
    {
        $cache = new EntityCache();
        $user = UserFixture::create();

        $cache->addEntity($user, 1);
        $cache->clear();

        self::assertNull($cache->getEntity(UserFixture::class, 1));
    }

    public function testAddMultipleEntities(): void
    {
        $cache = new EntityCache();
        $user1 = UserFixture::create(firstName: 'John');
        $user2 = UserFixture::create(firstName: 'Jane');

        $cache->addEntity($user1, 1);
        $cache->addEntity($user2, 2);

        self::assertSame($user1, $cache->getEntity(UserFixture::class, 1));
        self::assertSame($user2, $cache->getEntity(UserFixture::class, 2));
    }
}
