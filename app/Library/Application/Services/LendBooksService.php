<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{BookRepository, Clock, IdentifierGenerator, LoanRepository, MemberRepository, Transaction};
use App\Library\Domain\{DomainError, Loan};
final readonly class LendBooksService
{
    public function __construct(private MemberRepository $members, private BookRepository $books, private LoanRepository $loans, private Transaction $transaction, private Clock $clock, private IdentifierGenerator $ids) {}
    /** @param list<string> $bookIds */
    public function execute(string $memberId, array $bookIds, string $userId): Loan
    {
        if (count($bookIds) < 1 || count($bookIds) > 3) { throw new DomainError('VALIDATION_ERROR', 'El préstamo admite de uno a tres libros.'); }
        if (count(array_unique($bookIds)) !== count($bookIds)) { throw new DomainError('DUPLICATE_BOOK_IN_LOAN', 'No se puede repetir un libro en el préstamo.'); }
        $now = $this->clock->now();
        return $this->transaction->run(function () use ($memberId, $bookIds, $userId, $now) {
            if (!$this->members->lockMember($memberId)) { throw new DomainError('NOT_FOUND', 'No se encontró el socio.'); }
            $pending = $this->loans->outstanding($memberId, $now);
            if ($pending['overdue']) { throw new DomainError('MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos sin devolver.'); }
            if ($pending['count'] + count($bookIds) > 3) {
                throw new DomainError('MEMBER_LIMIT_EXCEEDED', 'El socio tiene '.$pending['count'].' libros sin devolver; el máximo es 3.', [['field' => 'member_id', 'issue' => (string) $pending['count']]]);
            }
            // Stable write order also avoids cyclic waits for multi-book requests.
            sort($bookIds, SORT_STRING);
            $loan = new Loan($this->ids->next(), $memberId, $userId, $now);
            foreach ($bookIds as $id) {
                $book = $this->books->findBook($id) ?? throw new DomainError('NOT_FOUND', 'No se encontró el libro.');
                $book->withdraw();
                $loan->addItem($this->ids->next(), $book);
                $this->books->saveBook($book);
            }
            $loan->record();
            $this->loans->addLoan($loan);
            return $loan;
        });
    }
}
