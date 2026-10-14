<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'manager_library_test') { throw new RuntimeException('Only the disposable test database is allowed.'); }
$job = json_decode(trim(fgets(STDIN)), true, 512, JSON_THROW_ON_ERROR);
if ($job['barrier'] ?? false) {
    $inner = $app->make(\App\Library\Application\Ports\BookRepository::class);
    $app->instance(\App\Library\Application\Ports\BookRepository::class, new class($inner) implements \App\Library\Application\Ports\BookRepository {
        private bool $first = true;
        public function __construct(private \App\Library\Application\Ports\BookRepository $inner) {}
        public function findBook(string $id): ?\App\Library\Domain\Book {
            $book = $this->inner->findBook($id);
            if ($this->first) { $this->first = false; echo "READ\n"; fflush(STDOUT); fgets(STDIN); }
            return $book;
        }
        public function addBook(\App\Library\Domain\Book $book): void { $this->inner->addBook($book); }
        public function saveBook(\App\Library\Domain\Book $book): void { $this->inner->saveBook($book); }
        public function searchBooks(string $query, ?string $categoryId, \App\Library\Application\Pagination $pagination): \App\Library\Application\Page { return $this->inner->searchBooks($query, $categoryId, $pagination); }
    });
}
echo "READY\n"; fflush(STDOUT); fgets(STDIN);
try {
    if ($job['action'] === 'lend') {
        $loan = $app->make(\App\Library\Application\Services\LendBooksService::class)->execute($job['member'], $job['books'], $job['user']);
        $result = ['code' => 'ok', 'id' => $loan->id];
    } else {
        $item = $app->make(\App\Library\Application\Services\ReturnItemService::class)->execute($job['loan'], $job['item']);
        $result = ['code' => 'ok', 'id' => $item->id];
    }
} catch (\App\Library\Domain\DomainError $error) { $result = ['code' => $error->errorCode]; }
echo 'RESULT '.json_encode($result, JSON_THROW_ON_ERROR)."\n";
