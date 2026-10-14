<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\Ports\LoanQueries;
use App\Library\Application\{LoanFilter, Page, Pagination};
use DateTimeImmutable;
class MemoryLoanQueries extends MemoryLoans implements LoanQueries
{
    public function searchLoans(LoanFilter $filter, Pagination $pagination, DateTimeImmutable $now): Page
    {
        $rows = array_values(array_filter($this->loans, static fn ($loan) =>
            ($filter->start === null || $loan->loanedAt >= $filter->start) &&
            ($filter->endExclusive === null || $loan->loanedAt < $filter->endExclusive) &&
            ($filter->memberId === null || $loan->memberId === $filter->memberId) &&
            ($filter->status === null || $loan->status($now) === $filter->status)));
        usort($rows, static fn ($a, $b) => [$b->loanedAt, $b->id] <=> [$a->loanedAt, $a->id]);
        return new Page(unserialize(serialize(array_slice($rows, $pagination->offset(), $pagination->size))), count($rows), $pagination);
    }
    public function overdue(Pagination $pagination, DateTimeImmutable $now): Page
    {
        $rows = [];
        foreach ($this->loans as $loan) {
            foreach ($loan->items() as $item) {
                if ($item->isOverdue($now)) {
                    $rows[] = ['loan_id' => $loan->id, 'item_id' => $item->id, 'book_title' => $item->bookTitle, 'member_name' => $this->members[$loan->memberId]->fullName, 'due_at' => $item->dueAt, 'days_late' => (int) floor(($now->getTimestamp() - $item->dueAt->getTimestamp()) / 86400)];
                }
            }
        }
        usort($rows, static fn ($a, $b) => [$a['due_at'], $a['item_id']] <=> [$b['due_at'], $b['item_id']]);
        return new Page(array_slice($rows, $pagination->offset(), $pagination->size), count($rows), $pagination);
    }
}
