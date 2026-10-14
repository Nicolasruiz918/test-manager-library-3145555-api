<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{BookRepository, Clock, LoanRepository, MemberRepository, Transaction};
use App\Library\Domain\{DomainError, LoanItem};
final readonly class ReturnItemService
{
    public function __construct(private LoanRepository $loans, private MemberRepository $members, private BookRepository $books, private Transaction $transaction, private Clock $clock) {}
    public function execute(string $loanId, string $itemId): LoanItem
    {
        $now = $this->clock->now();
        return $this->transaction->run(function () use ($loanId, $itemId, $now) {
            $loan = $this->loans->findLoan($loanId) ?? throw new DomainError('NOT_FOUND', 'No se encontró el préstamo.');
            $this->members->lockMember($loan->memberId);
            // A prior transaction may have returned an item before this lock was acquired.
            $loan = $this->loans->findLoan($loanId) ?? throw new DomainError('NOT_FOUND', 'No se encontró el préstamo.');
            $item = $loan->returnItem($itemId, $now);
            $book = $this->books->findBook($item->bookId) ?? throw new DomainError('NOT_FOUND', 'No se encontró el libro.');
            $book->restock();
            $this->books->saveBook($book);
            $this->loans->saveReturn($loanId, $item);
            return $item;
        });
    }
}
