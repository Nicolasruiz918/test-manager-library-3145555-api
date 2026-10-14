<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Library\Domain\{Book, DomainError};
use App\Library\Domain\ValueObjects\{Isbn, Quantity};
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BookTest extends TestCase
{
    private function book(int $total = 2, ?int $available = null): Book
    {
        return new Book('book-1', '  Original  ', 'Author', new Isbn('1234567890123'), 'category-1', 'Novela', new Quantity($total), $available ?? $total);
    }

    public function test_last_copy_reaches_zero_and_cannot_be_lent_again(): void
    {
        $book = $this->book(1);
        $book->withdraw();
        $this->assertSame(0, $book->availableCopies);
        try {
            $book->withdraw();
            $this->fail('A nonexistent copy was lent');
        } catch (DomainError $error) {
            $this->assertSame('BOOK_NOT_AVAILABLE', $error->errorCode);
            $this->assertSame(0, $book->availableCopies);
        }
    }

    public function test_restock_never_exceeds_total(): void
    {
        $book = $this->book(1, 0);
        $book->restock();
        $this->assertSame(1, $book->availableCopies);
        try {
            $book->restock();
            $this->fail('Inventory exceeded the total');
        } catch (DomainError) {
            $this->assertSame(1, $book->availableCopies);
        }
    }

    public function test_restock_adjustment_preserves_borrowed_quantity(): void
    {
        $book = $this->book(4, 1);
        $book->revise(' New ', 'Author', 'category-1', 'Novela', new Quantity(6));
        $this->assertSame(3, $book->availableCopies);
        $this->assertSame('New', $book->title);
        $book->revise('New', 'Author', 'category-1', 'Novela', new Quantity(3));
        $this->assertSame(0, $book->availableCopies);
        try {
            $book->revise('Must not apply', 'Author', 'category-1', 'Novela', new Quantity(2));
            $this->fail('Total was reduced below outstanding copies');
        } catch (DomainError $error) {
            $this->assertSame('VALIDATION_ERROR', $error->errorCode);
            $this->assertSame(3, $book->totalCopies);
            $this->assertSame(0, $book->availableCopies);
            $this->assertSame('New', $book->title);
        }
    }

    public function test_outstanding_copy_blocks_soft_deletion_regardless_of_due_date(): void
    {
        $book = $this->book(2, 1);
        try {
            $book->softDelete(new DateTimeImmutable('2026-10-14T12:00:00Z'));
            $this->fail('Book with an outstanding copy was deleted');
        } catch (DomainError $error) {
            $this->assertSame('BOOK_HAS_OPEN_LOANS', $error->errorCode);
            $this->assertNull($book->deletedAt);
        }
    }

    public function test_soft_deletion_retains_identity_and_rejects_new_withdrawals(): void
    {
        $book = $this->book();
        $now = new DateTimeImmutable('2026-10-14T12:00:00Z');
        $book->softDelete($now);
        $this->assertSame($now, $book->deletedAt);
        $this->assertSame('book-1', $book->id);
        $this->expectException(DomainError::class);
        $book->withdraw();
    }

    public function test_title_is_trimmed_and_cover_is_null_until_stored(): void
    {
        $book = $this->book();
        $this->assertSame('Original', $book->title);
        $this->assertNull($book->coverKey);
        $this->expectException(DomainError::class);
        $book->setCoverKey('');
    }

    public function test_invalid_hydrated_inventory_is_rejected(): void
    {
        $this->expectException(DomainError::class);
        $this->book(1, 2);
    }
}
