<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\Ports\{LoanQueries, LoanRepository};
use App\Library\Application\{LoanFilter, Page, Pagination};
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
final class PgLoanQueries extends PgGateway implements LoanQueries
{
    public function __construct(ConnectionInterface $db, private LoanRepository $loans) { parent::__construct($db); }
    public function searchLoans(LoanFilter $filter, Pagination $pagination, DateTimeImmutable $now): Page
    {
        $sql = $this->db->table('library.loan as l');
        if ($filter->start) { $sql->where('l.loaned_at', '>=', $filter->start->format('Y-m-d H:i:s.uP')); }
        if ($filter->endExclusive) { $sql->where('l.loaned_at', '<', $filter->endExclusive->format('Y-m-d H:i:s.uP')); }
        if ($filter->memberId) { $sql->where('l.member_id', $filter->memberId); }
        if ($filter->status) {
            $pending = static fn ($q) => $q->selectRaw('1')->from('library.loan_item as i')->whereColumn('i.loan_id', 'l.id')->whereNull('i.returned_at');
            $late = static fn ($q) => $pending($q)->where('i.due_at', '<', $now->format('Y-m-d H:i:s.uP'));
            if ($filter->status === 'closed') { $sql->whereNotExists($pending); }
            if ($filter->status === 'overdue') { $sql->whereExists($late); }
            if ($filter->status === 'open') { $sql->whereExists($pending)->whereNotExists($late); }
        }
        $total = (clone $sql)->count();
        $ids = $sql->orderByDesc('l.loaned_at')->orderByDesc('l.id')->offset($pagination->offset())->limit($pagination->size)->pluck('l.id');
        return new Page($ids->map(fn ($id) => $this->loans->findLoan($id))->all(), $total, $pagination);
    }
    public function overdue(Pagination $pagination, DateTimeImmutable $now): Page
    {
        $sql = $this->db->table('library.loan_item as i')->join('library.loan as l', 'l.id', '=', 'i.loan_id')->join('library.member as m', 'm.id', '=', 'l.member_id')
            ->whereNull('i.returned_at')->where('i.due_at', '<', $now->format('Y-m-d H:i:s.uP'));
        $total = (clone $sql)->count();
        $rows = $sql->select('l.id as loan_id', 'i.id as item_id', 'i.book_title', 'm.full_name as member_name', 'i.due_at')->orderBy('i.due_at')->orderBy('i.id')->offset($pagination->offset())->limit($pagination->size)->get()->map(static function ($row) use ($now) {
            $due = new DateTimeImmutable($row->due_at);
            return ['loan_id' => $row->loan_id, 'item_id' => $row->item_id, 'book_title' => $row->book_title, 'member_name' => $row->member_name, 'due_at' => $due, 'days_late' => (int) floor(($now->getTimestamp() - $due->getTimestamp()) / 86400)];
        })->all();
        return new Page($rows, $total, $pagination);
    }
}
