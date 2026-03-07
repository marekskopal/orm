<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Mapper;

use MarekSkopal\ORM\Mapper\Collection;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Collection::class)]
final class CollectionTest extends TestCase
{
    public function testCount(): void
    {
        $collection = new Collection([UserFixture::create(), UserFixture::create()]);
        self::assertCount(2, $collection);
    }

    public function testCountEmpty(): void
    {
        $collection = new Collection();
        self::assertCount(0, $collection);
    }

    public function testIterator(): void
    {
        $user1 = UserFixture::create(firstName: 'John');
        $user2 = UserFixture::create(firstName: 'Jane');
        $collection = new Collection([$user1, $user2]);

        $items = iterator_to_array($collection);
        self::assertCount(2, $items);
        self::assertSame($user1, $items[0]);
        self::assertSame($user2, $items[1]);
    }

    public function testRewind(): void
    {
        $user1 = UserFixture::create(firstName: 'John');
        $user2 = UserFixture::create(firstName: 'Jane');
        $collection = new Collection([$user1, $user2]);

        iterator_to_array($collection);
        $collection->rewind();

        self::assertSame($user1, $collection->current());
    }

    public function testCurrentOnEmpty(): void
    {
        $collection = new Collection();
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertFalse($collection->current());
    }

    public function testOffsetExists(): void
    {
        $collection = new Collection([UserFixture::create()]);
        self::assertTrue($collection->offsetExists(0));
        self::assertFalse($collection->offsetExists(1));
    }

    public function testOffsetGet(): void
    {
        $user = UserFixture::create();
        $collection = new Collection([$user]);
        self::assertSame($user, $collection->offsetGet(0));
    }

    public function testOffsetSetWithKey(): void
    {
        $user = UserFixture::create();
        $collection = new Collection();
        $collection->offsetSet(0, $user);
        /** @phpstan-ignore-next-line staticMethod.impossibleType */
        self::assertSame($user, $collection->offsetGet(0));
    }

    public function testOffsetSetWithoutKey(): void
    {
        $user = UserFixture::create();
        $collection = new Collection();
        $collection->offsetSet(null, $user);
        /** @phpstan-ignore-next-line staticMethod.impossibleType */
        self::assertSame($user, $collection->offsetGet(0));
    }

    public function testOffsetUnset(): void
    {
        $collection = new Collection([UserFixture::create()]);
        $collection->offsetUnset(0);
        self::assertFalse($collection->offsetExists(0));
    }

    public function testValid(): void
    {
        $collection = new Collection([UserFixture::create()]);
        self::assertTrue($collection->valid());
        $collection->next();
        self::assertFalse($collection->valid());
    }

    public function testKey(): void
    {
        $collection = new Collection([UserFixture::create(), UserFixture::create()]);
        self::assertSame(0, $collection->key());
        $collection->next();
        self::assertSame(1, $collection->key());
    }
}
