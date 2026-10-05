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

    public function testClearRunsListeners(): void
    {
        $cache = new EntityCache();
        $calls = 0;
        $cache->onClear(static function () use (&$calls): void {
            $calls++;
        });

        $cache->clear();
        $cache->clear();

        self::assertSame(2, $calls);
    }

    public function testIdentityMapReferenceSharesStorage(): void
    {
        $cache = new EntityCache();
        $user = UserFixture::create();

        $identityMap = &$cache->getIdentityMap();
        $identityMap[UserFixture::class][3] = $user;
        self::assertSame($user, $cache->getEntity(UserFixture::class, 3));

        $cache->clear();
        self::assertNull($cache->getEntity(UserFixture::class, 3));

        // Clearing reassigns the storage; the reference must still point at the live map.
        $identityMap[UserFixture::class][4] = $user;
        self::assertSame($user, $cache->getEntity(UserFixture::class, 4));
    }
}
