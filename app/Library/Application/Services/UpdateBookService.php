<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{BookRepository, CategoryCatalog, Transaction};
use App\Library\Domain\{Book, DomainError};
use App\Library\Domain\ValueObjects\Quantity;
final readonly class UpdateBookService
{
    public function __construct(private BookRepository $books, private CategoryCatalog $categories, private Transaction $transaction) {}
    public function execute(string $id, string $title, string $author, string $categoryId, int $total): Book
    {
        return $this->transaction->run(function () use ($id, $title, $author, $categoryId, $total) {
            $book = $this->books->findBook($id) ?? throw new DomainError('NOT_FOUND', 'No se encontró el libro.');
            $category = $this->categories->category($categoryId) ?? throw new DomainError('NOT_FOUND', 'No se encontró la categoría.');
            $book->revise($title, $author, $categoryId, $category['name'], new Quantity($total));
            $this->books->saveBook($book);
            return $book;
        });
    }
}
