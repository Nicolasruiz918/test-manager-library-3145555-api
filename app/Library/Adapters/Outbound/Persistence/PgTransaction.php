<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\Ports\Transaction;
use Illuminate\Database\{ConnectionInterface, QueryException};
final readonly class PgTransaction implements Transaction
{
    public function __construct(private ConnectionInterface $db) {}
    public function run(callable $operation): mixed
    {
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            try { return $this->db->transaction($operation); }
            catch (VersionConflict) {}
            catch (QueryException $error) {
                if (!in_array($error->errorInfo[0] ?? '', ['40001', '40P01'], true)) { throw $error; }
            }
        }
        throw new BusyOperation('Concurrent operation retry limit reached.');
    }
}
