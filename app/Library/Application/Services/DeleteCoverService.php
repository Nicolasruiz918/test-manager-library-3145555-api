<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{BookRepository, CoverStorage, Transaction};
use App\Library\Domain\DomainError;
final readonly class DeleteCoverService
{
    public function __construct(private BookRepository $books, private CoverStorage $covers, private Transaction $transaction) {}
    public function execute(string $id): void
    {
        $key = $this->transaction->run(function () use ($id) {
            $book = $this->books->findBook($id) ?? throw new DomainError('NOT_FOUND', 'No se encontró el libro.');
            $key = $book->coverKey;
            $book->setCoverKey(null);
            $this->books->saveBook($book);
            return $key;
        });
        if ($key !== null) { $this->covers->remove($key); }
    }
}
