<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Library\Application\Services\{CreateBookService, CreateMemberService, GetMemberService, LendBooksService, SearchMembersService};
use App\Library\Application\Pagination;
use App\Library\Domain\DomainError;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\{FrozenClock, MemoryLoans, SequenceIds};
final class LendingServicesTest extends TestCase
{
    private MemoryLoans $store;
    private FrozenClock $clock;
    private SequenceIds $ids;
    private LendBooksService $lend;
    protected function setUp(): void
    {
        $this->store = new MemoryLoans(); $this->ids = new SequenceIds(); $this->clock = new FrozenClock(new DateTimeImmutable('2026-10-01T12:00:00Z'));
        $this->lend = new LendBooksService($this->store, $this->store, $this->store, $this->store, $this->clock, $this->ids);
    }
    private function member(): string { return (new CreateMemberService($this->store, $this->ids))->execute('Ana Ruiz', '001')->id; }
    private function book(int $number, int $total = 3): string { return (new CreateBookService($this->store, $this->store, $this->ids))->execute('Book '.$number, 'Author', str_pad((string) $number, 13, '0', STR_PAD_LEFT), '11111111-1111-4111-8111-111111111111', $total)->id; }
    public function test_limit_counts_items_across_headers_and_reports_current_count(): void
    {
        $member = $this->member(); $books = [$this->book(1), $this->book(2), $this->book(3), $this->book(4)];
        $this->lend->execute($member, [$books[0], $books[1]], 'operator');
        $this->lend->execute($member, [$books[2]], 'operator');
        $this->assertSame(3, (new GetMemberService($this->store, $this->store, $this->clock))->execute($member)['active_loans']);
        try { $this->lend->execute($member, [$books[3]], 'operator'); $this->fail('Fourth item accepted'); }
        catch (DomainError $error) {
            $this->assertSame('MEMBER_LIMIT_EXCEEDED', $error->errorCode);
            $this->assertSame('3', $error->details[0]['issue']);
            $this->assertStringContainsString('3', $error->getMessage());
        }
        $this->assertSame(2, $this->store->loanCount()); $this->assertSame(3, $this->store->findBook($books[3])->availableCopies);
    }
    public function test_later_unavailable_item_rolls_back_earlier_inventory_write(): void
    {
        $member = $this->member(); $first = $this->book(1); $last = $this->book(2, 1);
        $book = $this->store->findBook($last); $book->withdraw(); $this->store->saveBook($book);
        try { $this->lend->execute($member, [$first, $last], 'operator'); $this->fail('Unavailable loan accepted'); }
        catch (DomainError $error) { $this->assertSame('BOOK_NOT_AVAILABLE', $error->errorCode); }
        $this->assertSame(3, $this->store->findBook($first)->availableCopies); $this->assertSame(0, $this->store->loanCount());
    }
    public function test_overdue_member_cannot_borrow_even_with_capacity(): void
    {
        $member = $this->member(); $first = $this->book(1); $second = $this->book(2);
        $loan = $this->lend->execute($member, [$first], 'operator');
        $this->assertSame('operator', $loan->loanedByUserId);
        $this->assertSame('2026-10-15T12:00:00+00:00', $loan->items()[0]->dueAt->format(DATE_ATOM));
        $this->clock->instant = new DateTimeImmutable('2026-10-15T12:00:01Z');
        try { $this->lend->execute($member, [$second], 'operator'); $this->fail('Overdue member accepted'); }
        catch (DomainError $error) { $this->assertSame('MEMBER_HAS_OVERDUE', $error->errorCode); }
        $this->assertSame(1, $this->store->loanCount());
    }
    public function test_duplicate_and_empty_requests_do_not_write_anything(): void
    {
        $member = $this->member(); $book = $this->book(1);
        foreach ([[[$book, $book], 'DUPLICATE_BOOK_IN_LOAN'], [[], 'VALIDATION_ERROR']] as [$items, $code]) {
            try { $this->lend->execute($member, $items, 'operator'); $this->fail('Invalid loan accepted'); }
            catch (DomainError $error) { $this->assertSame($code, $error->errorCode); }
        }
        $this->assertSame(0, $this->store->loanCount()); $this->assertSame(3, $this->store->findBook($book)->availableCopies);
    }
    public function test_member_document_uniqueness_and_search_are_preserved(): void
    {
        $create = new CreateMemberService($this->store, $this->ids);
        $create->execute('Ana Ruiz', '001'); $create->execute('Other', '002');
        $page = (new SearchMembersService($this->store))->execute('ANA', new Pagination());
        $this->assertSame(1, $page->total); $this->assertSame('001', $page->items[0]->documentNumber);
        $this->assertSame(1, (new SearchMembersService($this->store))->execute('002', new Pagination())->total);
        try { $create->execute('Different name', '001'); $this->fail('Duplicate document accepted'); }
        catch (DomainError $error) { $this->assertSame('DOCUMENT_TAKEN', $error->errorCode); }
    }
}
