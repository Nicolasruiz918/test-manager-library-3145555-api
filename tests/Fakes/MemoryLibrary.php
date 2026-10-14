<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\Ports\{BookRepository, CategoryCatalog, Transaction};
use App\Library\Application\{Page, Pagination};
use App\Library\Domain\{Book, DomainError};
use Throwable;
class MemoryLibrary implements BookRepository, CategoryCatalog, Transaction
{
    protected array $books = [];
    protected array $categoryRows = [
        ['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Novela'],
        ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Ciencia'],
    ];
    public function category(string $id): ?array
    {
        foreach ($this->categoryRows as $category) { if ($category['id'] === $id) { return $category; } }
        return null;
    }
    public function categories(): array { $rows = $this->categoryRows; usort($rows, fn ($a, $b) => $a['name'] <=> $b['name']); return $rows; }
    public function findBook(string $id): ?Book
    {
        $book = $this->books[$id] ?? null;
        return $book && $book->deletedAt === null ? clone $book : null;
    }
    public function addBook(Book $book): void
    {
        foreach ($this->books as $existing) {
            if ($existing->deletedAt === null && $existing->isbn->value === $book->isbn->value) { throw new DomainError('ISBN_TAKEN', 'El ISBN ya está registrado.'); }
        }
        $this->books[$book->id] = clone $book;
    }
    public function saveBook(Book $book): void { $this->books[$book->id] = clone $book; }
    public function searchBooks(string $query, ?string $categoryId, Pagination $pagination): Page
    {
        $rows = array_values(array_filter($this->books, static fn (Book $book) =>
            $book->deletedAt === null && ($categoryId === null || $categoryId === $book->categoryId) &&
            ($query === '' || mb_stripos($book->title, $query) !== false || mb_stripos($book->author, $query) !== false)));
        usort($rows, static fn (Book $a, Book $b) => [$a->title, $a->id] <=> [$b->title, $b->id]);
        return new Page(array_map(static fn ($book) => clone $book, array_slice($rows, $pagination->offset(), $pagination->size)), count($rows), $pagination);
    }
    public function run(callable $operation): mixed
    {
        $snapshot = serialize(get_object_vars($this));
        try { return $operation(); }
        catch (Throwable $error) {
            foreach (unserialize($snapshot) as $name => $value) { $this->$name = $value; }
            throw $error;
        }
    }
}
