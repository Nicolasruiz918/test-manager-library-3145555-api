<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\Ports\BookRepository;
use App\Library\Application\{Page, Pagination};
use App\Library\Domain\Book;
use App\Library\Domain\ValueObjects\{Isbn, Quantity};
use DateTimeImmutable;
use WeakMap;
final class PgBookRepository extends PgGateway implements BookRepository
{
    private ?WeakMap $versions = null;
    private function query()
    {
        return $this->db->table('library.book as b')->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->whereNull('b.deleted_at')->select('b.*', 'c.name as category_name')->selectRaw('b.xmin::text AS version_token');
    }
    public function findBook(string $id): ?Book
    {
        $row = $this->query()->where('b.id', $id)->first();
        return $row ? $this->hydrate($row) : null;
    }
    private function hydrate(object $row): Book
    {
        $book = new Book($row->id, $row->title, $row->author, new Isbn($row->isbn), $row->category_id, $row->category_name, new Quantity((int) $row->total_copies), (int) $row->available_copies, $row->cover_key, $row->deleted_at ? new DateTimeImmutable($row->deleted_at) : null);
        $this->versions ??= new WeakMap(); $this->versions[$book] = $row->version_token;
        return $book;
    }
    private function data(Book $book): array
    {
        return ['title' => $book->title, 'author' => $book->author, 'category_id' => $book->categoryId, 'total_copies' => $book->totalCopies, 'available_copies' => $book->availableCopies, 'cover_key' => $book->coverKey, 'deleted_at' => $book->deletedAt?->format('Y-m-d H:i:s.uP')];
    }
    public function addBook(Book $book): void
    {
        $this->insertUnique('library.book', ['id' => $book->id, 'isbn' => $book->isbn->value] + $this->data($book), 'uq_book_active_isbn', 'ISBN_TAKEN', 'El ISBN ya está registrado.');
    }
    public function saveBook(Book $book): void
    {
        if ($this->db->transactionLevel() === 0) { throw new \LogicException('Book changes require an application transaction.'); }
        $version = $this->versions[$book] ?? throw new \LogicException('Book must be loaded before an update.');
        $updated = $this->db->table('library.book')->where('id', $book->id)->whereRaw('xmin::text = ?', [$version])->update($this->data($book));
        if ($updated !== 1) { throw new VersionConflict('Book changed since it was read.'); }
        $this->versions[$book] = $this->db->table('library.book')->where('id', $book->id)->selectRaw('xmin::text AS version_token')->first()->version_token;
    }
    public function searchBooks(string $query, ?string $categoryId, Pagination $pagination): Page
    {
        $sql = $this->query();
        if ($query !== '') { $pattern = self::pattern($query); $sql->where(fn ($q) => $q->where('b.title', 'ilike', $pattern)->orWhere('b.author', 'ilike', $pattern)); }
        if ($categoryId !== null) { $sql->where('b.category_id', $categoryId); }
        $total = (clone $sql)->count();
        $rows = $sql->orderBy('b.title')->orderBy('b.id')->offset($pagination->offset())->limit($pagination->size)->get();
        return new Page($rows->map(fn ($row) => $this->hydrate($row))->all(), $total, $pagination);
    }
}
