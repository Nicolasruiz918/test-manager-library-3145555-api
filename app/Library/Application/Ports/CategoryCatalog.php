<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
interface CategoryCatalog
{
    public function category(string $id): ?array;
    public function categories(): array;
}
