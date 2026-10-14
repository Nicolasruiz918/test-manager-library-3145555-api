<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{BookRepository, Clock, Transaction};
use App\Library\Domain\DomainError;
final readonly class DeleteBookService
{
    public function __construct(private BookRepository $books, private Clock $clock, private Transaction $transaction) {}
    public function execute(string $id): void
    {
        $this->transaction->run(function () use ($id) {
            $book = $this->books->findBook($id) ?? throw new DomainError('NOT_FOUND', 'No se encontró el libro.');
            $book->softDelete($this->clock->now());
            $this->books->saveBook($book);
        });
    }
}
