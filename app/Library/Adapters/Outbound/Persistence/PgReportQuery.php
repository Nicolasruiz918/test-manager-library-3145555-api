<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\Ports\ReportQuery;
use App\Library\Domain\ValueObjects\DateRange;
final class PgReportQuery extends PgGateway implements ReportQuery
{
    public function mostBorrowed(DateRange $range): array
    {
        return $this->db->table('library.loan_item as i')->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.loaned_at', '>=', $range->start->format('Y-m-d H:i:s.uP'))->where('l.loaned_at', '<', $range->endExclusive->format('Y-m-d H:i:s.uP'))
            ->select('i.book_id', 'i.book_title', 'i.category_name')->selectRaw('COUNT(*) AS times_borrowed')
            ->groupBy('i.book_id', 'i.book_title', 'i.category_name')->orderByDesc('times_borrowed')->orderBy('i.book_title')->orderBy('i.book_id')->orderBy('i.category_name')
            ->get()->map(static fn ($row) => ['book_id' => $row->book_id, 'book_title' => $row->book_title, 'category_name' => $row->category_name, 'times_borrowed' => (int) $row->times_borrowed])->all();
    }
}
