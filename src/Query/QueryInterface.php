<?php

namespace MarekSkopal\ORM\Query;

interface QueryInterface
{
    public function getSql(): string;
}
