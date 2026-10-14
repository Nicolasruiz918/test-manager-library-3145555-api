<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\BookRepository;
use App\Library\Application\{Page, Pagination};
final readonly class SearchBooksService
{
    public function __construct(private BookRepository $books) {}
    public function execute(string $query, ?string $categoryId, Pagination $pagination): Page { return $this->books->searchBooks($query, $categoryId, $pagination); }
}
