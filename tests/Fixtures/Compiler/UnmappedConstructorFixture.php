<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Compiler;

/** An entity whose constructor needs a value that no column provides; it cannot be hydrated. */
final class UnmappedConstructorFixture
{
    public int $id;

    public function __construct(public string $name, string $required)
    {
        $this->name .= $required;
    }
}
