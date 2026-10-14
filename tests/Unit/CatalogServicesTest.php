<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Library\Application\Services\{CreateBookService, DeleteBookService, DeleteCoverService, GetBookService, GetCoverService, PutCoverService, SearchBooksService, UpdateBookService};
use App\Library\Application\Pagination;
use App\Library\Domain\DomainError;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\{FrozenClock, MemoryCovers, MemoryLibrary, SequenceIds};
final class CatalogServicesTest extends TestCase
{
    private const CATEGORY = '11111111-1111-4111-8111-111111111111';
    public function test_catalog_search_filters_and_paginates_with_total(): void
    {
        $store = new MemoryLibrary(); $create = new CreateBookService($store, $store, new SequenceIds());
        $create->execute('B title', 'Ana', '0000000000001', self::CATEGORY, 1);
        $create->execute('A title', 'Ana', '0000000000002', self::CATEGORY, 2);
        $create->execute('Other', 'Someone', '0000000000003', '22222222-2222-4222-8222-222222222222', 2);
        $page = (new SearchBooksService($store))->execute('ANA', self::CATEGORY, new Pagination(1, 1));
        $this->assertSame(2, $page->total); $this->assertCount(1, $page->items); $this->assertSame('A title', $page->items[0]->title);
        $this->assertSame(2, $page->items[0]->availableCopies);
    }
    public function test_duplicate_active_isbn_and_reuse_after_soft_deletion(): void
    {
        $store = new MemoryLibrary(); $create = new CreateBookService($store, $store, new SequenceIds());
        $book = $create->execute('Title', 'Author', '0000000000001', self::CATEGORY, 1);
        try { $create->execute('Title', 'Author', '0000000000001', self::CATEGORY, 1); $this->fail('Duplicate accepted'); }
        catch (DomainError $error) { $this->assertSame('ISBN_TAKEN', $error->errorCode); }
        (new DeleteBookService($store, new FrozenClock(new DateTimeImmutable('2026-10-14Z')), $store))->execute($book->id);
        $this->assertNull($store->findBook($book->id));
        $newBook = $create->execute('Title', 'Author', '0000000000001', self::CATEGORY, 1);
        $this->assertNotSame($book->id, $newBook->id);
        try { (new GetBookService($store))->execute($book->id); $this->fail('Deleted book returned'); }
        catch (DomainError $error) { $this->assertSame('NOT_FOUND', $error->errorCode); }
    }
    public function test_failed_edit_and_delete_preserve_outstanding_inventory(): void
    {
        $store = new MemoryLibrary(); $book = (new CreateBookService($store, $store, new SequenceIds()))->execute('Original', 'Author', '0000000000001', self::CATEGORY, 2);
        $book->withdraw(); $book->withdraw(); $store->saveBook($book);
        try { (new UpdateBookService($store, $store, $store))->execute($book->id, 'Changed', 'Author', self::CATEGORY, 1); $this->fail('Invalid total accepted'); }
        catch (DomainError $error) { $this->assertSame('VALIDATION_ERROR', $error->errorCode); }
        $this->assertSame('Original', $store->findBook($book->id)->title);
        try { (new DeleteBookService($store, new FrozenClock(new DateTimeImmutable('2026-10-14Z')), $store))->execute($book->id); $this->fail('Pending copy deleted'); }
        catch (DomainError $error) { $this->assertSame('BOOK_HAS_OPEN_LOANS', $error->errorCode); }
        $updated = (new UpdateBookService($store, $store, $store))->execute($book->id, 'Changed', 'Author', self::CATEGORY, 3);
        $this->assertSame(1, $updated->availableCopies);
        $this->assertSame('0000000000001', $updated->isbn->value);
    }
    public function test_cover_replacement_and_removal_leave_nullable_keys(): void
    {
        $store = new MemoryLibrary(); $covers = new MemoryCovers();
        $book = (new CreateBookService($store, $store, new SequenceIds()))->execute('Title', 'Author', '0000000000001', self::CATEGORY, 1);
        $this->assertNull($book->coverKey);
        $put = new PutCoverService($store, $covers, $store);
        $first = $put->execute($book->id, 'first image');
        $second = $put->execute($book->id, 'second image');
        $this->assertArrayNotHasKey($first->coverKey, $covers->files);
        $this->assertSame('second image', (new GetCoverService($store, $covers))->execute($book->id)['bytes']);
        (new DeleteCoverService($store, $covers, $store))->execute($book->id);
        (new DeleteCoverService($store, $covers, $store))->execute($book->id);
        $this->assertNull($store->findBook($book->id)->coverKey); $this->assertSame([], $covers->files);
        $this->expectException(DomainError::class); (new GetCoverService($store, $covers))->execute($book->id);
    }
}
