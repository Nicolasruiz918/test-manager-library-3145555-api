<?php

declare(strict_types=1);

namespace App\Library\Domain;

use DateTimeImmutable;
use DateTimeZone;

final class Loan
{
    /** @var list<LoanItem> */
    private array $items = [];
    private bool $recorded = false;
    public readonly DateTimeImmutable $loanedAt;

    public function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        DateTimeImmutable $loanedAt,
    ) {
        $this->loanedAt = $loanedAt->setTimezone(new DateTimeZone('UTC'));
    }

    public function addItem(string $itemId, Book $book): void
    {
        if ($this->recorded) {
            throw new DomainError('VALIDATION_ERROR', 'Un préstamo registrado no se puede editar.');
        }
        foreach ($this->items as $item) {
            if ($item->bookId === $book->id) {
                throw new DomainError('DUPLICATE_BOOK_IN_LOAN', 'No se puede repetir un libro en el préstamo.');
            }
        }
        if (count($this->items) >= 3) {
            throw new DomainError('VALIDATION_ERROR', 'El préstamo admite de uno a tres libros.');
        }
        $this->items[] = new LoanItem($itemId, $book->id, $book->title, $book->isbn->value, $book->categoryName, $this->loanedAt->modify('+14 days'));
    }

    public function record(): void
    {
        if ($this->items === []) {
            throw new DomainError('VALIDATION_ERROR', 'El préstamo debe incluir al menos un libro.');
        }
        $this->recorded = true;
    }

    /** @param list<LoanItem> $items */
    public static function restore(string $id, string $memberId, string $userId, DateTimeImmutable $loanedAt, array $items): self
    {
        $loan = new self($id, $memberId, $userId, $loanedAt);
        if (count($items) < 1 || count($items) > 3 || count(array_unique(array_map(static fn (LoanItem $item) => $item->bookId, $items))) !== count($items)) {
            throw new DomainError('VALIDATION_ERROR', 'Las líneas del préstamo no son válidas.');
        }
        foreach ($items as $item) {
            if ($item->dueAt != $loan->loanedAt->modify('+14 days')) {
                throw new DomainError('VALIDATION_ERROR', 'El vencimiento del préstamo no corresponde a catorce días.');
            }
        }
        $loan->items = array_values($items);
        $loan->record();
        return $loan;
    }

    /** @return list<LoanItem> */
    public function items(): array { return $this->items; }

    public function status(DateTimeImmutable $now): string
    {
        $this->requireRecorded();
        $pending = false;
        foreach ($this->items as $item) {
            if ($item->isOverdue($now)) {
                return 'overdue';
            }
            $pending = $pending || $item->returnedAt === null;
        }
        return $pending ? 'open' : 'closed';
    }

    public function returnItem(string $itemId, DateTimeImmutable $now): LoanItem
    {
        $this->requireRecorded();
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                $item->returnAt($now);
                return $item;
            }
        }
        throw new DomainError('NOT_FOUND', 'No se encontró la línea del préstamo.');
    }

    private function requireRecorded(): void
    {
        if (!$this->recorded) {
            throw new DomainError('VALIDATION_ERROR', 'El préstamo aún no está registrado.');
        }
    }
}
