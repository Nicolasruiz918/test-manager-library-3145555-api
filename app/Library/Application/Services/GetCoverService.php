<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{BookRepository, CoverStorage};
use App\Library\Domain\DomainError;
final readonly class GetCoverService
{
    public function __construct(private BookRepository $books, private CoverStorage $covers) {}
    public function execute(string $id): array
    {
        $book = $this->books->findBook($id);
        if (!$book || $book->coverKey === null) { throw new DomainError('NOT_FOUND', 'No se encontró la portada.'); }
        return $this->covers->read($book->coverKey);
    }
}
