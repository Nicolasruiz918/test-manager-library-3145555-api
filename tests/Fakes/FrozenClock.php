<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Library\Application\Ports\Clock;
use DateTimeImmutable;

final class FrozenClock implements Clock
{
    public function __construct(public DateTimeImmutable $instant) {}
    public function now(): DateTimeImmutable { return $this->instant; }
}
