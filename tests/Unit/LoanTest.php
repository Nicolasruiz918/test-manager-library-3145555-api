<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Library\Domain\{Book, DomainError, Loan};
use App\Library\Domain\ValueObjects\{Isbn, Quantity};
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FrozenClock;

final class LoanTest extends TestCase
{
    private function book(string $id = 'b1'): Book
    {
        return new Book($id, 'Original title', 'Author', new Isbn('1234567890123'), 'c1', 'Novela', new Quantity(2), 2);
    }

    public function test_states_with_fake_clock_and_strict_fourteen_day_boundary(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-10-01T10:00:00-05:00'));
        $loan = new Loan('l1', 'm1', 'u1', $clock->now());
        $loan->addItem('i1', $this->book());
        $loan->addItem('i2', $this->book('b2'));
        $loan->record();
        $this->assertSame('2026-10-01T15:00:00+00:00', $loan->loanedAt->format('c'));
        $this->assertSame('2026-10-15T15:00:00+00:00', $loan->items()[0]->dueAt->format('c'));
        $this->assertSame('open', $loan->status($clock->now()));
        $clock->instant = new DateTimeImmutable('2026-10-15T15:00:00Z');
        $this->assertSame('open', $loan->status($clock->now()));
        $clock->instant = $clock->instant->modify('+1 microsecond');
        $this->assertSame('overdue', $loan->status($clock->now()));
        $loan->returnItem('i1', $clock->now());
        $this->assertSame('overdue', $loan->status($clock->now()));
        $loan->returnItem('i2', $clock->now());
        $this->assertSame('closed', $loan->status($clock->now()));
    }

    public function test_duplicate_return_preserves_the_first_return_instant(): void
    {
        $now = new DateTimeImmutable('2026-10-01T15:00:00Z');
        $loan = new Loan('l1', 'm1', 'u1', $now);
        $loan->addItem('i1', $this->book());
        $loan->record();
        $item = $loan->returnItem('i1', $now);
        try {
            $loan->returnItem('i1', $now->modify('+1 day'));
            $this->fail('Duplicate return accepted');
        } catch (DomainError $error) {
            $this->assertSame('ITEM_ALREADY_RETURNED', $error->errorCode);
            $this->assertEquals($now, $item->returnedAt);
        }
    }

    public function test_empty_loan_cannot_be_recorded(): void
    {
        $loan = new Loan('l1', 'm1', 'u1', new DateTimeImmutable('2026-10-01Z'));
        $this->expectException(DomainError::class);
        $loan->record();
    }

    public function test_duplicate_book_is_rejected(): void
    {
        $loan = new Loan('l1', 'm1', 'u1', new DateTimeImmutable('2026-10-01Z'));
        $loan->addItem('i1', $this->book());
        try {
            $loan->addItem('i2', $this->book());
            $this->fail('Duplicate book accepted');
        } catch (DomainError $error) {
            $this->assertSame('DUPLICATE_BOOK_IN_LOAN', $error->errorCode);
            $this->assertCount(1, $loan->items());
        }
    }

    public function test_three_items_are_allowed_and_four_are_rejected(): void
    {
        $loan = new Loan('l1', 'm1', 'u1', new DateTimeImmutable('2026-10-01Z'));
        foreach (range(1, 3) as $n) {
            $loan->addItem('i'.$n, $this->book('b'.$n));
        }
        $this->assertCount(3, $loan->items());
        $this->expectException(DomainError::class);
        $loan->addItem('i4', $this->book('b4'));
    }

    public function test_snapshot_survives_catalog_changes(): void
    {
        $book = $this->book();
        $loan = new Loan('l1', 'm1', 'u1', new DateTimeImmutable('2026-10-01Z'));
        $loan->addItem('i1', $book);
        $loan->record();
        $book->revise('Renamed', 'New author', 'c2', 'Ciencia', new Quantity(3));
        $this->assertSame('Original title', $loan->items()[0]->bookTitle);
        $this->assertSame('Novela', $loan->items()[0]->categoryName);
        $this->assertSame('1234567890123', $loan->items()[0]->isbn);
        $this->expectException(DomainError::class);
        $loan->addItem('i2', $this->book('b2'));
    }
}
