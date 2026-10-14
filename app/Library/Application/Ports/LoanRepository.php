<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use App\Library\Domain\{Loan, LoanItem};
use DateTimeImmutable;
interface LoanRepository
{
    public function addLoan(Loan $loan): void;
    public function findLoan(string $id): ?Loan;
    /** @return array{count:int,overdue:bool} */
    public function outstanding(string $memberId, DateTimeImmutable $now): array;
    public function saveReturn(string $loanId, LoanItem $item): void;
}
