<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Library\Application\Ports\{Clock, IdentifierGenerator, LoanRepository, UserRepository};
use App\Library\Application\Services\{CreateBookService, CreateMemberService, LendBooksService, MostBorrowedService, OverdueService, ReturnItemService, SearchLoansService, UpdateBookService};
use App\Library\Application\{LoanFilter, Pagination};
use App\Library\Domain\{DomainError, User};
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FrozenClock;
final class LoanPersistenceTest extends DatabaseTestCase
{
    public function test_real_queries_preserve_snapshots_and_calculate_overdue_and_closed_states(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-10-01T23:59:59Z')); $this->app->instance(Clock::class, $clock);
        $user = new User($this->app->make(IdentifierGenerator::class)->next(), 'operator', 'fake-test-hash', 'librarian'); $this->app->make(UserRepository::class)->addUser($user);
        $member = $this->app->make(CreateMemberService::class)->execute('Ana', '001');
        $book = $this->app->make(CreateBookService::class)->execute('Original', 'Author', '0000000000001', '11111111-1111-4111-8111-111111111111', 2);
        $loan = $this->app->make(LendBooksService::class)->execute($member->id, [$book->id], $user->id);
        $this->app->make(UpdateBookService::class)->execute($book->id, 'Renamed', 'Author', '22222222-2222-4222-8222-222222222222', 2);
        $report = $this->app->make(MostBorrowedService::class)->execute('2026-10-01', '2026-10-01');
        $this->assertSame([['book_id' => $book->id, 'book_title' => 'Original', 'category_name' => 'Novela', 'times_borrowed' => 1]], $report['rows']);
        $clock->instant = new DateTimeImmutable('2026-10-16T23:59:59Z');
        $page = $this->app->make(OverdueService::class)->execute(new Pagination());
        $this->assertSame(1, $page->total); $this->assertSame(1, $page->items[0]['days_late']);
        $this->assertSame('Ana', $page->items[0]['member_name']);
        $this->assertSame(1, $this->app->make(SearchLoansService::class)->execute(new LoanFilter('2026-10-01', '2026-10-01', 'overdue', $member->id), new Pagination())->total);
        $this->app->make(ReturnItemService::class)->execute($loan->id, $loan->items()[0]->id);
        $this->assertSame(0, $this->app->make(OverdueService::class)->execute(new Pagination())->total);
        $this->assertSame('closed', $this->app->make(LoanRepository::class)->findLoan($loan->id)->status($clock->now()));
        $this->assertSame(1, $this->app->make(SearchLoansService::class)->execute(new LoanFilter(null, null, 'closed'), new Pagination())->total);
        $this->assertSame($report, $this->app->make(MostBorrowedService::class)->execute('2026-10-01', '2026-10-01'));
    }
    public function test_failed_multi_item_operation_leaves_no_header_item_or_inventory_change(): void
    {
        $ids = $this->app->make(IdentifierGenerator::class);
        $user = new User($ids->next(), 'operator', 'fake-test-hash', 'librarian'); $this->app->make(UserRepository::class)->addUser($user);
        $member = $this->app->make(CreateMemberService::class)->execute('Ana', '001');
        $create = $this->app->make(CreateBookService::class);
        $a = $create->execute('A', 'Author', '0000000000001', '11111111-1111-4111-8111-111111111111', 1);
        $b = $create->execute('B', 'Author', '0000000000002', '11111111-1111-4111-8111-111111111111', 1);
        // The earlier id is processed first; the later one is deliberately unavailable.
        $bookIds = [$a->id, $b->id]; sort($bookIds);
        DB::table('library.book')->where('id', $bookIds[1])->update(['available_copies' => 0]);
        try { $this->app->make(LendBooksService::class)->execute($member->id, $bookIds, $user->id); $this->fail('Unavailable loan succeeded'); }
        catch (DomainError $error) { $this->assertSame('BOOK_NOT_AVAILABLE', $error->errorCode); }
        $this->assertSame(0, DB::table('library.loan')->count()); $this->assertSame(0, DB::table('library.loan_item')->count());
        $this->assertSame(1, DB::table('library.book')->where('id', $bookIds[0])->value('available_copies'));
    }
}
