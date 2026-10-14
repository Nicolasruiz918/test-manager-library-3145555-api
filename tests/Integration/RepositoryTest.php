<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Library\Application\Ports\{BookRepository, CategoryCatalog, IdentifierGenerator, MemberRepository, Transaction, UserRepository};
use App\Library\Application\Services\{CreateBookService, CreateMemberService, DeleteBookService, SearchBooksService, UpdateBookService};
use App\Library\Application\Pagination;
use App\Library\Domain\{DomainError, User};
use Illuminate\Support\Facades\DB;
final class RepositoryTest extends DatabaseTestCase
{
    private const CATEGORY = '11111111-1111-4111-8111-111111111111';
    public function test_catalog_round_trip_search_and_partial_isbn_uniqueness(): void
    {
        $create = $this->app->make(CreateBookService::class);
        $book = $create->execute('  Original  ', 'Author', '0000000000001', self::CATEGORY, 2);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $book->id);
        try { $create->execute('Duplicate', 'Other', '0000000000001', self::CATEGORY, 1); $this->fail('Duplicate accepted'); }
        catch (DomainError $error) { $this->assertSame('ISBN_TAKEN', $error->errorCode); }
        $this->assertSame(1, $this->app->make(SearchBooksService::class)->execute('AUTHOR', self::CATEGORY, new Pagination())->total);
        $this->assertSame(0, $this->app->make(SearchBooksService::class)->execute('%', null, new Pagination())->total);
        $updated = $this->app->make(UpdateBookService::class)->execute($book->id, 'New', 'Author', self::CATEGORY, 3);
        $this->assertSame(3, $updated->availableCopies);
        $this->app->make(DeleteBookService::class)->execute($book->id);
        $this->assertNull($this->app->make(BookRepository::class)->findBook($book->id));
        $this->assertNotNull(DB::table('library.book')->where('id', $book->id)->value('deleted_at'));
        $this->assertNotSame($book->id, $create->execute('Replacement', 'Author', '0000000000001', self::CATEGORY, 1)->id);
        $this->assertCount(5, $this->app->make(CategoryCatalog::class)->categories());
    }
    public function test_member_and_user_uniqueness_round_trip(): void
    {
        $create = $this->app->make(CreateMemberService::class); $member = $create->execute('Ana Ruiz', '001');
        $members = $this->app->make(MemberRepository::class);
        $this->assertSame('Ana Ruiz', $members->findMember($member->id)->fullName);
        $this->assertSame(1, $members->searchMembers('001', new Pagination())->total);
        try { $create->execute('Other', '001'); $this->fail('Duplicate document accepted'); }
        catch (DomainError $error) { $this->assertSame('DOCUMENT_TAKEN', $error->errorCode); }
        $ids = $this->app->make(IdentifierGenerator::class); $users = $this->app->make(UserRepository::class);
        $user = User::librarian($ids->next(), 'Ana ', 'fake-integration-hash', 'librarian'); $users->addUser($user);
        $this->assertSame('ana', $users->findByUsername('ana')->username);
        $this->assertSame('librarian', $users->findUser($user->id)->role);
        try { $users->addUser(User::librarian($ids->next(), ' ANA ', 'fake-integration-hash', 'librarian')); $this->fail('Duplicate user accepted'); }
        catch (DomainError $error) { $this->assertSame('USERNAME_TAKEN', $error->errorCode); }
    }
    public function test_transaction_rolls_back_catalog_changes_on_failure(): void
    {
        $create = $this->app->make(CreateBookService::class);
        try {
            $this->app->make(Transaction::class)->run(function () use ($create) { $create->execute('Rollback', 'Author', '0000000000001', self::CATEGORY, 1); throw new \RuntimeException('Intentional test rollback'); });
            $this->fail('Transaction succeeded');
        } catch (\RuntimeException $error) { $this->assertSame('Intentional test rollback', $error->getMessage()); }
        $this->assertSame(0, DB::table('library.book')->count());
    }
}
