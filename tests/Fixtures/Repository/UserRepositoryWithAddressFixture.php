<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Fixtures\Repository;

use MarekSkopal\ORM\Repository\AbstractRepository;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserWithAddressFixture;

/** @extends AbstractRepository<UserWithAddressFixture> */
class UserRepositoryWithAddressFixture extends AbstractRepository
{
}
