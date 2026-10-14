<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Domain\DomainError;
use Illuminate\Database\{ConnectionInterface, QueryException};
abstract class PgGateway
{
    public function __construct(protected ConnectionInterface $db) {}
    protected function insertUnique(string $table, array $data, string $constraint, string $code, string $message): void
    {
        try { $this->db->transaction(fn () => $this->db->table($table)->insert($data)); }
        catch (QueryException $error) {
            if (($error->errorInfo[0] ?? '') === '23505' && str_contains($error->errorInfo[2] ?? '', $constraint)) { throw new DomainError($code, $message); }
            throw $error;
        }
    }
    protected static function pattern(string $query): string { return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%'; }
}
