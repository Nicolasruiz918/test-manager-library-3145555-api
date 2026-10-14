<?php
declare(strict_types=1);
namespace App\Library\Application;
use App\Library\Domain\DomainError;
use App\Library\Domain\ValueObjects\DateRange;
use DateTimeImmutable;
final readonly class LoanFilter
{
    public ?DateTimeImmutable $start;
    public ?DateTimeImmutable $endExclusive;
    public function __construct(?string $from = null, ?string $to = null, public ?string $status = null, public ?string $memberId = null)
    {
        if ($status !== null && !in_array($status, ['open', 'overdue', 'closed'], true)) { throw new DomainError('VALIDATION_ERROR', 'El estado del préstamo no es válido.'); }
        if ($from !== null && $to !== null) { new DateRange($from, $to); }
        $this->start = $from !== null ? (new DateRange($from, $from))->start : null;
        $this->endExclusive = $to !== null ? (new DateRange($to, $to))->endExclusive : null;
    }
}
