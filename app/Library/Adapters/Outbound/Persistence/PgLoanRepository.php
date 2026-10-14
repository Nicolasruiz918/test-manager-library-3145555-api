<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\Ports\LoanRepository;
use App\Library\Domain\{DomainError, Loan, LoanItem};
use DateTimeImmutable;
final class PgLoanRepository extends PgGateway implements LoanRepository
{
    public function addLoan(Loan $loan): void
    {
        if ($this->db->transactionLevel() === 0) { throw new \LogicException('Loans require an application transaction.'); }
        $this->db->table('library.loan')->insert(['id' => $loan->id, 'member_id' => $loan->memberId, 'loaned_by_user_id' => $loan->loanedByUserId, 'loaned_at' => $loan->loanedAt->format('Y-m-d H:i:s.uP')]);
        $rows = [];
        foreach ($loan->items() as $item) {
            $rows[] = ['id' => $item->id, 'loan_id' => $loan->id, 'book_id' => $item->bookId, 'book_title' => $item->bookTitle, 'isbn' => $item->isbn, 'category_name' => $item->categoryName, 'due_at' => $item->dueAt->format('Y-m-d H:i:s.uP'), 'returned_at' => null];
        }
        $this->db->table('library.loan_item')->insert($rows);
    }
    public function findLoan(string $id): ?Loan
    {
        $row = $this->db->table('library.loan')->where('id', $id)->first();
        if (!$row) { return null; }
        $items = $this->db->table('library.loan_item')->where('loan_id', $id)->orderBy('id')->get()->map(static fn ($item) =>
            new LoanItem($item->id, $item->book_id, $item->book_title, $item->isbn, $item->category_name, new DateTimeImmutable($item->due_at), $item->returned_at ? new DateTimeImmutable($item->returned_at) : null))->all();
        return Loan::restore($row->id, $row->member_id, $row->loaned_by_user_id, new DateTimeImmutable($row->loaned_at), $items);
    }
    public function outstanding(string $memberId, DateTimeImmutable $now): array
    {
        $sql = $this->db->table('library.loan_item as i')->join('library.loan as l', 'l.id', '=', 'i.loan_id')->where('l.member_id', $memberId)->whereNull('i.returned_at');
        $row = $sql->selectRaw('COUNT(*) AS pending_count, COUNT(*) FILTER (WHERE i.due_at < ?) AS overdue_count', [$now->format('Y-m-d H:i:s.uP')])->first();
        return ['count' => (int) $row->pending_count, 'overdue' => (int) $row->overdue_count > 0];
    }
    public function saveReturn(string $loanId, LoanItem $item): void
    {
        if ($this->db->transactionLevel() === 0 || $item->returnedAt === null) { throw new \LogicException('Returns require an application transaction and instant.'); }
        $changed = $this->db->table('library.loan_item')->where('loan_id', $loanId)->where('id', $item->id)->whereNull('returned_at')->update(['returned_at' => $item->returnedAt->format('Y-m-d H:i:s.uP')]);
        if ($changed !== 1) {
            $row = $this->db->table('library.loan_item')->where('loan_id', $loanId)->where('id', $item->id)->first();
            if ($row && $row->returned_at !== null) { throw new DomainError('ITEM_ALREADY_RETURNED', 'El ejemplar ya fue devuelto.'); }
            throw new DomainError('NOT_FOUND', 'No se encontró la línea del préstamo.');
        }
    }
}
