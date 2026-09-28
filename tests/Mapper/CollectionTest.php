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

    public function testGetIteratorReturnsFreshCursor(): void
    {
        $collection = new Collection([UserFixture::create()]);

        self::assertNotSame($collection->getIterator(), $collection->getIterator());
    }

    public function testNestedIteration(): void
    {
        $collection = new Collection([UserFixture::create(), UserFixture::create(), UserFixture::create()]);

        $pairs = [];
        foreach ($collection as $outer) {
            foreach ($collection as $inner) {
                $pairs[] = [$outer, $inner];
            }
        }

        self::assertCount(9, $pairs);
    }

    public function testIterationAfterBreak(): void
    {
        $user1 = UserFixture::create(firstName: 'John');
        $user2 = UserFixture::create(firstName: 'Jane');
        $collection = new Collection([$user1, $user2]);

        foreach ($collection as $item) {
            self::assertSame($user1, $item);
            break;
        }

        self::assertSame([$user1, $user2], iterator_to_array($collection));
    }

    public function testToArray(): void
    {
        $user = UserFixture::create();
        $collection = new Collection(['a' => $user]);

        self::assertSame(['a' => $user], $collection->toArray());
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
}
