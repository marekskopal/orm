<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Entity;

use MarekSkopal\ORM\Entity\IdentityMap;
use MarekSkopal\ORM\Tests\Fixtures\Entity\Enum\UserTypeEnum;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(IdentityMap::class)]
final class IdentityMapTest extends TestCase
{
    public function testGetEntityReturnsNullWhenMissing(): void
    {
        $cache = new IdentityMap();
        self::assertNull($cache->get(UserFixture::class, 1));
    }

    public function testAddAndGetEntity(): void
    {
        $cache = new IdentityMap();
        $user = UserFixture::create();

        $cache->add($user, 1);

        self::assertSame($user, $cache->get(UserFixture::class, 1));
    }

    public function testGetEntityReturnsNullForDifferentId(): void
    {
        $cache = new IdentityMap();
        $user = UserFixture::create();

        $cache->add($user, 1);

        self::assertNull($cache->get(UserFixture::class, 2));
    }

    public function testClear(): void
    {
        $cache = new IdentityMap();
        $user = UserFixture::create();

        $cache->add($user, 1);
        $cache->clear();

        self::assertNull($cache->get(UserFixture::class, 1));
    }

    public function testAddMultipleEntities(): void
    {
        $cache = new IdentityMap();
        $user1 = UserFixture::create(firstName: 'John');
        $user2 = UserFixture::create(firstName: 'Jane');

        $cache->add($user1, 1);
        $cache->add($user2, 2);

        self::assertSame($user1, $cache->get(UserFixture::class, 1));
        self::assertSame($user2, $cache->get(UserFixture::class, 2));
    }

    public function testClearRunsListeners(): void
    {
        $cache = new IdentityMap();
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
        $cache = new IdentityMap();
        $user = UserFixture::create();

        $identityMap = &$cache->getEntitiesReference();
        $identityMap[UserFixture::class][3] = $user;
        self::assertSame($user, $cache->get(UserFixture::class, 3));

        $cache->clear();
        self::assertNull($cache->get(UserFixture::class, 3));

        // Clearing reassigns the storage; the reference must still point at the live map.
        $identityMap[UserFixture::class][4] = $user;
        self::assertSame($user, $cache->get(UserFixture::class, 4));
    }

    public function testKeysAreNormalised(): void
    {
        $uuid = Uuid::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');

        self::assertSame(5, IdentityMap::key(5));
        self::assertSame('abc', IdentityMap::key('abc'));
        self::assertSame('f47ac10b-58cc-4372-a567-0e02b2c3d479', IdentityMap::key($uuid));
        self::assertSame('admin', IdentityMap::key(UserTypeEnum::Admin));

        $cache = new IdentityMap();
        $user = UserFixture::create();
        $cache->add($user, $uuid);
        self::assertSame($user, $cache->get(UserFixture::class, 'f47ac10b-58cc-4372-a567-0e02b2c3d479'));

        $this->expectException(\InvalidArgumentException::class);
        IdentityMap::key([1]);
    }

    public function testSnapshotsAndRemoval(): void
    {
        $cache = new IdentityMap();
        $user = UserFixture::create();
        $other = UserFixture::create();

        $cache->add($user, 1);
        $cache->setSnapshot($user, ['first_name' => 'John']);
        self::assertTrue($cache->contains($user, 1));
        self::assertFalse($cache->contains($other, 1));
        self::assertTrue($cache->hasSnapshot($user));
        self::assertSame(['first_name' => 'John'], $cache->getSnapshot($user));

        // Removing another instance with the same id leaves the registered one alone.
        $cache->remove($other, 1);
        self::assertSame($user, $cache->get(UserFixture::class, 1));

        $cache->remove($user, 1);
        self::assertNull($cache->get(UserFixture::class, 1));
        self::assertNull($cache->getSnapshot($user));
    }

    public function testClearDropsSnapshots(): void
    {
        $cache = new IdentityMap();
        $user = UserFixture::create();
        $snapshots = &$cache->getSnapshotsReference();
        $snapshots[spl_object_id($user)] = ['first_name' => 'John'];
        self::assertTrue($cache->hasSnapshot($user));

        $cache->clear();
        self::assertFalse($cache->hasSnapshot($user));

        // The reference still points at the live storage after clear().
        $snapshots[spl_object_id($user)] = [];
        self::assertTrue($cache->hasSnapshot($user));
    }

    public function testMoveSnapshot(): void
    {
        $cache = new IdentityMap();
        $from = UserFixture::create();
        $to = UserFixture::create();
        $cache->setSnapshot($from, ['a' => 1]);

        $cache->moveSnapshot($from, $to);

        self::assertFalse($cache->hasSnapshot($from));
        self::assertSame(['a' => 1], $cache->getSnapshot($to));
    }
}
