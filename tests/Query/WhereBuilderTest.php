<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Query;

use MarekSkopal\ORM\Query\WhereBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WhereBuilder::class)]
final class WhereBuilderTest extends TestCase
{
    public function testBuild(): void
    {
        $whereBuilder = new WhereBuilder();

        $whereBuilder->where([
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        self::assertSame(
            'id=? AND first_name=? AND last_name=?',
            $whereBuilder->build(),
        );
    }

    public function testGetParams(): void
    {
        $whereBuilder = new WhereBuilder();

        $whereBuilder->where([
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        self::assertSame(
            [1, 'John', 'Doe'],
            $whereBuilder->getParams(),
        );
    }

    public function testBuildOr(): void
    {
        $whereBuilder = new WhereBuilder();

        $whereBuilder->where([
            'id' => 1,
        ]);
        $whereBuilder->orWhere([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        self::assertSame(
            'id=? OR first_name=? AND last_name=?',
            $whereBuilder->build(),
        );
    }

    public function testBuildOrOr(): void
    {
        $whereBuilder = new WhereBuilder();

        $whereBuilder->where([
            'id' => 1,
        ]);
        $whereBuilder->orWhere(
            ['first_name' => 'John'],
        );
        $whereBuilder->orWhere(
            ['last_name' => 'Doe'],
        );

        self::assertSame(
            'id=? OR first_name=? OR last_name=?',
            $whereBuilder->build(),
        );
    }

    public function testBuildSub(): void
    {
        // id=1 AND ((first_name='John' AND last_name='Doe') OR (first_name='Jane' AND last_name='Doe'))

        $whereBuilder = new WhereBuilder();

        $whereBuilder->where([
            'id' => 1,
        ]);
        $whereBuilder->where(fn(WhereBuilder $builder) => $builder
            ->where([
                'first_name' => 'John',
                'last_name' => 'Doe',
            ])
            ->orWhere([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
            ]),);

        self::assertSame(
            'id=? AND (first_name=? AND last_name=? OR first_name=? AND last_name=?)',
            $whereBuilder->build(),
        );
    }

    public function testGetParamsSub(): void
    {
        $whereBuilder = new WhereBuilder();

        $whereBuilder->where([
            'id' => 1,
        ]);
        $whereBuilder->where(fn(WhereBuilder $builder) => $builder
            ->where([
                'first_name' => 'John',
                'last_name' => 'Doe',
            ])
            ->orWhere([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
            ]),);

        self::assertSame(
            [1, 'John', 'Doe', 'Jane', 'Doe'],
            $whereBuilder->getParams(),
        );
    }

    public function testBuildIn(): void
    {
        $whereBuilder = new WhereBuilder();

        $whereBuilder->where([
            'id',
            'IN',
            [1, 2, 3],
        ]);

        self::assertSame(
            'id IN (?,?,?)',
            $whereBuilder->build(),
        );
    }
}
