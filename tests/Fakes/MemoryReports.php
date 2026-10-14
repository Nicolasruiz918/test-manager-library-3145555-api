<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\Ports\ReportQuery;
use App\Library\Domain\ValueObjects\DateRange;
final class MemoryReports extends MemoryLoanQueries implements ReportQuery
{
    public function mostBorrowed(DateRange $range): array
    {
        $rows = [];
        foreach ($this->loans as $loan) {
            if (!$range->contains($loan->loanedAt)) { continue; }
            foreach ($loan->items() as $item) {
                $key = json_encode([$item->bookId, $item->bookTitle, $item->categoryName]);
                $rows[$key] ??= ['book_id' => $item->bookId, 'book_title' => $item->bookTitle, 'category_name' => $item->categoryName, 'times_borrowed' => 0];
                ++$rows[$key]['times_borrowed'];
            }
        }
        $rows = array_values($rows);
        usort($rows, static fn ($a, $b) => [-$a['times_borrowed'], $a['book_title'], $a['book_id'], $a['category_name']] <=> [-$b['times_borrowed'], $b['book_title'], $b['book_id'], $b['category_name']]);
        return $rows;
    }
}
