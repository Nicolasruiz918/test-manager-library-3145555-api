<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Library\Application\Services\{CreateBookService, CreateMemberService, LendBooksService, MostBorrowedService, UpdateBookService};
use App\Library\Domain\DomainError;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\{FrozenClock, MemoryReports, SequenceIds};
final class ReportServiceTest extends TestCase
{
    public function test_report_preserves_old_title_and_groups_snapshots_without_people(): void
    {
        $store = new MemoryReports(); $ids = new SequenceIds(); $clock = new FrozenClock(new DateTimeImmutable('2026-10-01T23:59:59Z'));
        $member = (new CreateMemberService($store, $ids))->execute('Private Person', '001');
        $book = (new CreateBookService($store, $store, $ids))->execute('Old title', 'Author', '0000000000001', '11111111-1111-4111-8111-111111111111', 3);
        $lend = new LendBooksService($store, $store, $store, $store, $clock, $ids);
        $lend->execute($member->id, [$book->id], 'operator');
        $report = new MostBorrowedService($store);
        $old = $report->execute('2026-10-01', '2026-10-01');
        (new UpdateBookService($store, $store, $store))->execute($book->id, 'New title', 'Author', '22222222-2222-4222-8222-222222222222', 3);
        $clock->instant = new DateTimeImmutable('2026-10-02T00:00:00Z');
        $lend->execute($member->id, [$book->id], 'operator'); $lend->execute($member->id, [$book->id], 'operator');
        $this->assertSame($old, $report->execute('2026-10-01', '2026-10-01'));
        $rows = $report->execute('2026-10-01', '2026-10-02')['rows'];
        $this->assertCount(2, $rows); $this->assertSame('New title', $rows[0]['book_title']); $this->assertSame(2, $rows[0]['times_borrowed']);
        $this->assertSame('Novela', $rows[1]['category_name']);
        $this->assertSame(['book_id', 'book_title', 'category_name', 'times_borrowed'], array_keys($rows[0]));
        $this->assertStringNotContainsString('Private Person', json_encode($rows));
        $this->assertSame([], $report->execute('2026-09-01', '2026-09-30')['rows']);
    }
    public function test_reversed_range_has_the_contract_error(): void
    {
        try { (new MostBorrowedService(new MemoryReports()))->execute('2026-10-02', '2026-10-01'); $this->fail('Reversed range accepted'); }
        catch (DomainError $error) { $this->assertSame('INVALID_DATE_RANGE', $error->errorCode); }
    }
}
