<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Application\Page;
use App\Library\Domain\{Book, Loan, LoanItem, Member};
use DateTimeImmutable;
use DateTimeZone;
final class Presenter
{
    public static function date(DateTimeImmutable $date): string { return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'); }
    public static function book(Book $book): array
    {
        return ['id' => $book->id, 'title' => $book->title, 'author' => $book->author, 'isbn' => $book->isbn->value, 'category' => ['id' => $book->categoryId, 'name' => $book->categoryName], 'total_copies' => $book->totalCopies, 'available_copies' => $book->availableCopies, 'cover_key' => $book->coverKey];
    }
    public static function member(Member $member): array { return ['id' => $member->id, 'full_name' => $member->fullName, 'document_number' => $member->documentNumber]; }
    public static function item(LoanItem $item): array
    {
        return ['id' => $item->id, 'book_id' => $item->bookId, 'book_title' => $item->bookTitle, 'isbn' => $item->isbn, 'category_name' => $item->categoryName, 'due_at' => self::date($item->dueAt), 'returned_at' => $item->returnedAt ? self::date($item->returnedAt) : null];
    }
    public static function loan(Loan $loan, DateTimeImmutable $now): array { return ['id' => $loan->id, 'member_id' => $loan->memberId, 'loaned_at' => self::date($loan->loanedAt), 'status' => $loan->status($now), 'items' => array_map(self::item(...), $loan->items())]; }
    public static function page(Page $page, callable $map): array { return ['items' => array_map($map, $page->items), 'page' => $page->pagination->page, 'size' => $page->pagination->size, 'total' => $page->total]; }
}
