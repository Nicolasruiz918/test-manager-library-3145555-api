<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{BookRepository, CategoryCatalog, IdentifierGenerator};
use App\Library\Domain\{Book, DomainError};
use App\Library\Domain\ValueObjects\{Isbn, Quantity};
final readonly class CreateBookService
{
    public function __construct(private BookRepository $books, private CategoryCatalog $categories, private IdentifierGenerator $ids) {}
    public function execute(string $title, string $author, string $isbn, string $categoryId, int $total): Book
    {
        $category = $this->categories->category($categoryId) ?? throw new DomainError('NOT_FOUND', 'No se encontró la categoría.');
        $book = new Book($this->ids->next(), $title, $author, new Isbn($isbn), $categoryId, $category['name'], new Quantity($total), $total);
        $this->books->addBook($book);
        return $book;
    }
}
