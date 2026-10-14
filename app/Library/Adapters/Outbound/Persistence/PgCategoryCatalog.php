<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\Ports\CategoryCatalog;
final class PgCategoryCatalog extends PgGateway implements CategoryCatalog
{
    public function category(string $id): ?array { $row = $this->db->table('library.category')->where('id', $id)->first(); return $row ? (array) $row : null; }
    public function categories(): array { return $this->db->table('library.category')->orderBy('name')->get()->map(static fn ($row) => (array) $row)->all(); }
}
