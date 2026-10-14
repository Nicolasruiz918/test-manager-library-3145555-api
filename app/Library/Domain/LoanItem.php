<?php

declare(strict_types=1);

namespace App\Library\Domain;

use DateTimeImmutable;
use DateTimeZone;

final class LoanItem
{
    public private(set) ?DateTimeImmutable $returnedAt;

    public function __construct(
        public readonly string $id,
        public readonly string $bookId,
        public readonly string $bookTitle,
        public readonly string $isbn,
        public readonly string $categoryName,
        public readonly DateTimeImmutable $dueAt,
        ?DateTimeImmutable $returnedAt = null,
    ) {
        $this->returnedAt = $returnedAt;
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return $this->returnedAt === null && $this->dueAt < $now;
    }

    public function returnAt(DateTimeImmutable $now): void
    {
        if ($this->returnedAt !== null) {
            throw new DomainError('ITEM_ALREADY_RETURNED', 'Este ejemplar ya fue devuelto.');
        }
        $this->returnedAt = $now->setTimezone(new DateTimeZone('UTC'));
    }
}
