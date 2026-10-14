<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Runtime;
use App\Library\Application\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;
final readonly class BusinessClock implements Clock
{
    private ?DateTimeImmutable $fixed;
    public function __construct(string $environment, string $historicalInstant = '')
    {
        if ($historicalInstant !== '' && !in_array($environment, ['demo', 'testing'], true)) { throw new \LogicException('Business-clock simulation is restricted to demo/testing environments.'); }
        if ($historicalInstant !== '' && !preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z\z/', $historicalInstant)) { throw new \LogicException('DEMO_BUSINESS_TIME must be an explicit UTC instant.'); }
        $this->fixed = $historicalInstant === '' ? null : new DateTimeImmutable($historicalInstant);
    }
    public function now(): DateTimeImmutable { return $this->fixed ?? new DateTimeImmutable('now', new DateTimeZone('UTC')); }
}
