<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use App\Library\Domain\Book;
use App\Library\Application\{Page, Pagination};
interface BookRepository
{
    public function findBook(string $id): ?Book;
    public function addBook(Book $book): void;
    public function saveBook(Book $book): void;
    public function searchBooks(string $query, ?string $categoryId, Pagination $pagination): Page;
}
