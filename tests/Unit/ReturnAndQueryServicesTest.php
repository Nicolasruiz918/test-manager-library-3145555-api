<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Library\Application\Services\{CreateBookService, CreateMemberService, GetLoanService, LendBooksService, OverdueService, ReturnItemService, SearchLoansService};
use App\Library\Application\{LoanFilter, Pagination};
use App\Library\Domain\DomainError;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\{FrozenClock, MemoryLoanQueries, SequenceIds};
final class ReturnAndQueryServicesTest extends TestCase
{
    private MemoryLoanQueries $store;
    private FrozenClock $clock;
    private ReturnItemService $returns;
    private LendBooksService $lend;
    private string $member;
    private array $books;
    protected function setUp(): void
    {
        $this->store = new MemoryLoanQueries(); $ids = new SequenceIds(); $this->clock = new FrozenClock(new DateTimeImmutable('2026-10-01T12:00:00Z'));
        $this->member = (new CreateMemberService($this->store, $ids))->execute('Ana Ruiz', '001')->id;
        $create = new CreateBookService($this->store, $this->store, $ids);
        $this->books = [$create->execute('First', 'Author', '0000000000001', '11111111-1111-4111-8111-111111111111', 2)->id, $create->execute('Second', 'Author', '0000000000002', '11111111-1111-4111-8111-111111111111', 1)->id];
        $this->lend = new LendBooksService($this->store, $this->store, $this->store, $this->store, $this->clock, $ids);
        $this->returns = new ReturnItemService($this->store, $this->store, $this->store, $this->store, $this->clock);
    }
    public function test_returns_close_only_after_every_item_and_never_increment_twice(): void
    {
        $loan = $this->lend->execute($this->member, $this->books, 'user');
        $item = $this->returns->execute($loan->id, $loan->items()[0]->id);
        $this->assertEquals($this->clock->instant, $item->returnedAt);
        $this->assertSame('open', (new GetLoanService($this->store))->execute($loan->id)->status($this->clock->now()));
        $available = $this->store->findBook($item->bookId)->availableCopies;
        try { $this->returns->execute($loan->id, $item->id); $this->fail('Duplicate return accepted'); }
        catch (DomainError $error) { $this->assertSame('ITEM_ALREADY_RETURNED', $error->errorCode); }
        $this->assertSame($available, $this->store->findBook($item->bookId)->availableCopies);
        $this->returns->execute($loan->id, $loan->items()[1]->id);
        $this->assertSame('closed', $this->store->findLoan($loan->id)->status($this->clock->now()));
    }
    public function test_item_from_another_loan_is_not_returned(): void
    {
        $first = $this->lend->execute($this->member, [$this->books[0]], 'user');
        $second = $this->lend->execute($this->member, [$this->books[1]], 'user');
        try { $this->returns->execute($first->id, $second->items()[0]->id); $this->fail('Foreign item returned'); }
        catch (DomainError $error) { $this->assertSame('NOT_FOUND', $error->errorCode); }
        $this->assertNull($this->store->findLoan($second->id)->items()[0]->returnedAt);
        $this->assertSame(0, $this->store->findBook($this->books[1])->availableCopies);
    }
    public function test_overdue_boundary_ordering_and_exclusion_after_return(): void
    {
        $first = $this->lend->execute($this->member, [$this->books[0]], 'user');
        $this->clock->instant = new DateTimeImmutable('2026-10-02T12:00:00Z');
        $second = $this->lend->execute($this->member, [$this->books[1]], 'user');
        $overdue = new OverdueService($this->store, $this->clock);
        $this->clock->instant = new DateTimeImmutable('2026-10-15T12:00:00Z');
        $this->assertSame(0, $overdue->execute(new Pagination())->total);
        $this->clock->instant = new DateTimeImmutable('2026-10-17T11:00:00Z');
        $page = $overdue->execute(new Pagination());
        $this->assertSame(2, $page->total); $this->assertSame('First', $page->items[0]['book_title']); $this->assertSame(1, $page->items[0]['days_late']); $this->assertSame(0, $page->items[1]['days_late']);
        $this->returns->execute($first->id, $first->items()[0]->id);
        $this->assertSame(1, $overdue->execute(new Pagination())->total);
        $this->assertSame($second->id, $overdue->execute(new Pagination())->items[0]['loan_id']);
    }
    public function test_loan_filters_include_end_day_and_calculate_status(): void
    {
        $first = $this->lend->execute($this->member, [$this->books[0]], 'user');
        $this->clock->instant = new DateTimeImmutable('2026-10-02T23:59:59Z');
        $second = $this->lend->execute($this->member, [$this->books[1]], 'user');
        $this->returns->execute($first->id, $first->items()[0]->id);
        $search = new SearchLoansService($this->store, $this->clock);
        $page = $search->execute(new LoanFilter('2026-10-01', '2026-10-02'), new Pagination());
        $this->assertSame(2, $page->total); $this->assertSame($second->id, $page->items[0]->id);
        $this->assertSame(1, $search->execute(new LoanFilter(null, null, 'closed', $this->member), new Pagination())->total);
        $this->assertSame(0, $search->execute(new LoanFilter('2026-10-03'), new Pagination())->total);
        $this->expectException(DomainError::class); new LoanFilter('2026-10-03', '2026-10-01');
    }
}
