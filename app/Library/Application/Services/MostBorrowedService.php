<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\ReportQuery;
use App\Library\Domain\ValueObjects\DateRange;
final readonly class MostBorrowedService
{
    public function __construct(private ReportQuery $query) {}
    public function execute(string $from, string $to): array
    {
        $range = new DateRange($from, $to);
        return ['from' => $from, 'to' => $to, 'rows' => $this->query->mostBorrowed($range)];
    }
}
