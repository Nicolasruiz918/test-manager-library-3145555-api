<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Runtime;
use App\Library\Application\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable { return new DateTimeImmutable('now', new DateTimeZone('UTC')); }
}
