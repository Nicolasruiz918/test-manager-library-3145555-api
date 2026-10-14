<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\BookRepository;
use App\Library\Domain\{Book, DomainError};
final readonly class GetBookService
{
    public function __construct(private BookRepository $books) {}
    public function execute(string $id): Book { return $this->books->findBook($id) ?? throw new DomainError('NOT_FOUND', 'No se encontró el libro.'); }
}
