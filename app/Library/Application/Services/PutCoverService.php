<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{BookRepository, CoverStorage, Transaction};
use App\Library\Domain\{Book, DomainError};
use Throwable;
final readonly class PutCoverService
{
    public function __construct(private BookRepository $books, private CoverStorage $covers, private Transaction $transaction) {}
    public function execute(string $id, string $path): Book
    {
        if (!$this->books->findBook($id)) { throw new DomainError('NOT_FOUND', 'No se encontró el libro.'); }
        $key = $this->covers->store($path);
        try {
            [$book, $previous] = $this->transaction->run(function () use ($id, $key) {
                $book = $this->books->findBook($id) ?? throw new DomainError('NOT_FOUND', 'No se encontró el libro.');
                $previous = $book->coverKey;
                $book->setCoverKey($key);
                $this->books->saveBook($book);
                return [$book, $previous];
            });
        } catch (Throwable $error) {
            $this->covers->remove($key);
            throw $error;
        }
        if ($previous !== null) { $this->covers->remove($previous); }
        return $book;
    }
}
