<?php

declare(strict_types=1);

namespace App\Library\Application\Ports;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
