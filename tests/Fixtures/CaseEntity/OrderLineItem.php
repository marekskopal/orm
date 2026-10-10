<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\CaseEntity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

/** No explicit table or column names, so the configured cases decide them. */
#[Entity]
final class OrderLineItem
{
    public function __construct(
        #[Column(type: Type::Int, primary: true, autoIncrement: true)]
        public int $id,
        #[Column(type: Type::String)]
        public string $unitPrice,
        #[ManyToOne(entityClass: self::class, nullable: true)]
        public ?OrderLineItem $parentItem,
    ) {
    }
}
