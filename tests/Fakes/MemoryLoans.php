<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\Ports\{LoanRepository, MemberRepository};
use App\Library\Application\{Page, Pagination};
use App\Library\Domain\{DomainError, Loan, LoanItem, Member};
use DateTimeImmutable;
class MemoryLoans extends MemoryLibrary implements LoanRepository, MemberRepository
{
    protected array $members = [];
    protected array $loans = [];
    public function findMember(string $id): ?Member { return $this->members[$id] ?? null; }
    public function lockMember(string $id): ?Member { return $this->findMember($id); }
    public function addMember(Member $member): void
    {
        foreach ($this->members as $existing) {
            if ($existing->documentNumber === $member->documentNumber) { throw new DomainError('DOCUMENT_TAKEN', 'El documento ya está registrado.'); }
        }
        $this->members[$member->id] = $member;
    }
    public function searchMembers(string $query, Pagination $pagination): Page
    {
        $rows = array_values(array_filter($this->members, static fn (Member $member) => $query === '' || mb_stripos($member->fullName, $query) !== false || mb_stripos($member->documentNumber, $query) !== false));
        usort($rows, static fn (Member $a, Member $b) => [$a->fullName, $a->id] <=> [$b->fullName, $b->id]);
        return new Page(array_slice($rows, $pagination->offset(), $pagination->size), count($rows), $pagination);
    }
    public function addLoan(Loan $loan): void { $this->loans[$loan->id] = unserialize(serialize($loan)); }
    public function findLoan(string $id): ?Loan { return isset($this->loans[$id]) ? unserialize(serialize($this->loans[$id])) : null; }
    public function outstanding(string $memberId, DateTimeImmutable $now): array
    {
        $count = 0; $overdue = false;
        foreach ($this->loans as $loan) {
            if ($loan->memberId !== $memberId) { continue; }
            foreach ($loan->items() as $item) {
                if ($item->returnedAt === null) { ++$count; $overdue = $overdue || $item->isOverdue($now); }
            }
        }
        return compact('count', 'overdue');
    }
    public function saveReturn(string $loanId, LoanItem $item): void
    {
        $loan = $this->loans[$loanId] ?? throw new DomainError('NOT_FOUND', 'No se encontró el préstamo.');
        $loan->returnItem($item->id, $item->returnedAt);
    }
    public function loanCount(): int { return count($this->loans); }
}
