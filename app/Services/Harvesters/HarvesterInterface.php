<?php

namespace App\Services\Harvesters;

interface HarvesterInterface
{
    public function harvest(array $source): array;
}
