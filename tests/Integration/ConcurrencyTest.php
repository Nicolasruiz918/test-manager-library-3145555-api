<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Library\Application\Ports\{BookRepository, Clock, IdentifierGenerator, LoanRepository, UserRepository};
use App\Library\Application\Services\{CreateBookService, CreateMemberService, LendBooksService};
use App\Library\Domain\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\{InputStream, Process};
use Tests\TestCase;
final class ConcurrencyTest extends TestCase
{
    private string $user;
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('DB_DATABASE') !== 'manager_library_test') { $this->markTestSkipped('Use the disposable PostgreSQL test stack.'); }
        $this->assertSame(0, $this->app->make(Kernel::class)->call('library:migrate'));
        $this->user = $this->app->make(IdentifierGenerator::class)->next();
        $this->app->make(UserRepository::class)->addUser(new User($this->user, 'operator', 'fake-test-hash', 'librarian'));
    }
    protected function tearDown(): void
    {
        if (getenv('DB_DATABASE') === 'manager_library_test') {
            DB::disconnect();
            // This class alone commits fixtures for independent worker connections.
            DB::unprepared('DROP SCHEMA IF EXISTS library CASCADE');
        }
        parent::tearDown();
    }
    private function member(string $document): string { return $this->app->make(CreateMemberService::class)->execute('Member '.$document, $document)->id; }
    private function book(int $isbn, int $copies = 1): string { return $this->app->make(CreateBookService::class)->execute('Book '.$isbn, 'Author', str_pad((string) $isbn, 13, '0', STR_PAD_LEFT), '11111111-1111-4111-8111-111111111111', $copies)->id; }
    private function lend(string $member, array $books) { return $this->app->make(LendBooksService::class)->execute($member, $books, $this->user); }
    private function concurrent(array $jobs): array
    {
        $processes = []; $inputs = [];
        try {
            foreach ($jobs as $job) {
                $input = new InputStream(); $input->write(json_encode($job + ['user' => $this->user])."\n");
                $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-worker.php')], base_path(), null, $input, 45);
                $process->start(); $processes[] = $process; $inputs[] = $input;
            }
            foreach ($processes as $process) {
                if (!str_contains($process->getOutput(), "READY\n")) { $process->waitUntil(static fn () => str_contains($process->getOutput(), "READY\n")); }
                $this->assertTrue($process->isRunning(), $process->getErrorOutput());
            }
            foreach ($inputs as $input) { $input->write("GO\n"); }
            if ($jobs[0]['barrier'] ?? false) {
                foreach ($processes as $process) {
                    if (!str_contains($process->getOutput(), "READ\n")) { $process->waitUntil(static fn () => str_contains($process->getOutput(), "READ\n")); }
                    $this->assertStringContainsString("READ\n", $process->getOutput(), $process->getErrorOutput());
                }
                foreach ($inputs as $input) { $input->write("WRITE\n"); }
            }
            $codes = [];
            foreach ($processes as $index => $process) {
                $inputs[$index]->close(); $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
                preg_match('/RESULT (.+)/', $process->getOutput(), $matches);
                $codes[] = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR)['code'];
            }
            sort($codes); return $codes;
        } finally { foreach ($processes as $process) { if ($process->isRunning()) { $process->stop(); } } }
    }
    public function test_two_reads_of_the_last_copy_produce_one_loan_and_one_availability_conflict(): void
    {
        $book = $this->book(1); $a = $this->member('001'); $b = $this->member('002');
        $codes = $this->concurrent([['action' => 'lend', 'member' => $a, 'books' => [$book], 'barrier' => true], ['action' => 'lend', 'member' => $b, 'books' => [$book], 'barrier' => true]]);
        $this->assertSame(['BOOK_NOT_AVAILABLE', 'ok'], $codes);
        $this->assertSame(1, DB::table('library.loan')->count()); $this->assertSame(1, DB::table('library.loan_item')->count());
        $this->assertSame(0, $this->app->make(BookRepository::class)->findBook($book)->availableCopies);
    }
    public function test_same_member_cannot_pass_three_items_with_concurrent_different_books(): void
    {
        $member = $this->member('001'); $books = [$this->book(1), $this->book(2), $this->book(3), $this->book(4)];
        $this->lend($member, [$books[0], $books[1]]);
        $codes = $this->concurrent([['action' => 'lend', 'member' => $member, 'books' => [$books[2]]], ['action' => 'lend', 'member' => $member, 'books' => [$books[3]]]]);
        $this->assertSame(['MEMBER_LIMIT_EXCEEDED', 'ok'], $codes);
        $this->assertSame(3, $this->app->make(LoanRepository::class)->outstanding($member, $this->app->make(Clock::class)->now())['count']);
    }
    public function test_simultaneous_duplicate_return_restores_exactly_one_copy(): void
    {
        $book = $this->book(1); $loan = $this->lend($this->member('001'), [$book]);
        $job = ['action' => 'return', 'loan' => $loan->id, 'item' => $loan->items()[0]->id];
        $this->assertSame(['ITEM_ALREADY_RETURNED', 'ok'], $this->concurrent([$job, $job]));
        $this->assertSame(1, $this->app->make(BookRepository::class)->findBook($book)->availableCopies);
        $this->assertNotNull($this->app->make(LoanRepository::class)->findLoan($loan->id)->items()[0]->returnedAt);
    }
    public function test_distinct_returns_of_the_same_book_do_not_lose_inventory_updates(): void
    {
        $book = $this->book(1, 2);
        $first = $this->lend($this->member('001'), [$book]); $second = $this->lend($this->member('002'), [$book]);
        $this->assertSame(['ok', 'ok'], $this->concurrent([
            ['action' => 'return', 'loan' => $first->id, 'item' => $first->items()[0]->id, 'barrier' => true],
            ['action' => 'return', 'loan' => $second->id, 'item' => $second->items()[0]->id, 'barrier' => true],
        ]));
        $this->assertSame(2, $this->app->make(BookRepository::class)->findBook($book)->availableCopies);
        $this->assertSame(0, DB::table('library.loan_item')->whereNull('returned_at')->count());
    }
}
