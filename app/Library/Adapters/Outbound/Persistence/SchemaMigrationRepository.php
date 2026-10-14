<?php

declare(strict_types=1);

namespace App\Library\Adapters\Outbound\Persistence;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use LogicException;
use RuntimeException;

/** Laravel migration history in existing schema metadata; no seventh table. */
final class SchemaMigrationRepository implements MigrationRepositoryInterface
{
    private ?string $source = null;
    private const KEY = 'manager_library_migrations_v1';

    public function __construct(private ConnectionResolverInterface $resolver) {}

    public function getRan(): array { return array_keys($this->getMigrationBatches()); }

    public function getMigrationBatches(): array
    {
        $raw = $this->comment();
        if ($raw === null) {
            return [];
        }
        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_keys($data) !== [self::KEY] || !is_array($data[self::KEY])) {
            throw new RuntimeException('Unrecognized library schema metadata; refusing to overwrite it.');
        }
        $history = $data[self::KEY];
        foreach ($history as $name => $batch) {
            if (!is_string($name) || !is_int($batch) || $batch < 1) {
                throw new RuntimeException('Invalid migration history.');
            }
        }
        ksort($history);
        asort($history, SORT_NUMERIC);
        return $history;
    }

    public function getMigrations($steps): array
    {
        $rows = [];
        foreach (array_reverse($this->getMigrationBatches(), true) as $migration => $batch) {
            $rows[] = (object) compact('migration', 'batch');
        }
        return array_slice($rows, 0, $steps);
    }

    public function getMigrationsByBatch($batch): array
    {
        return array_values(array_filter($this->getMigrations(PHP_INT_MAX), static fn ($row) => $row->batch === $batch));
    }

    public function getLast(): array { return $this->getMigrationsByBatch($this->getLastBatchNumber()); }
    public function getLastBatchNumber(): int { return max([0, ...array_values($this->getMigrationBatches())]); }
    public function getNextBatchNumber(): int { return $this->getLastBatchNumber() + 1; }

    public function log($file, $batch): void
    {
        $history = $this->getMigrationBatches();
        if (isset($history[$file])) {
            throw new LogicException('Migration already recorded.');
        }
        $history[$file] = $batch;
        $this->write($history);
    }

    public function delete($migration): void
    {
        $history = $this->getMigrationBatches();
        unset($history[$migration->migration]);
        $this->write($history);
    }

    public function createRepository(): void
    {
        $this->requireTransaction();
        $this->getConnection()->statement('CREATE SCHEMA IF NOT EXISTS library');
        if ($this->comment() !== null) {
            $this->getMigrationBatches();
            return;
        }
        $tables = $this->getConnection()->select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'library'");
        if ($tables !== []) {
            throw new RuntimeException('Library tables exist without migration history; inspect before adopting them.');
        }
        $this->write([]);
    }

    public function repositoryExists(): bool
    {
        if ($this->comment() === null) {
            return false;
        }
        $this->getMigrationBatches();
        return true;
    }

    public function deleteRepository(): void
    {
        throw new LogicException('Destructive migration reset is not supported.');
    }

    public function setSource($name): void { $this->source = $name; }
    public function getConnection() { return $this->resolver->connection($this->source); }

    private function comment(): ?string
    {
        $row = $this->getConnection()->selectOne("SELECT obj_description(oid, 'pg_namespace') AS metadata FROM pg_namespace WHERE nspname = 'library'");
        return $row?->metadata;
    }

    private function write(array $history): void
    {
        $this->requireTransaction();
        $json = json_encode([self::KEY => (object) $history], JSON_THROW_ON_ERROR);
        $quoted = $this->getConnection()->getPdo()->quote($json);
        $this->getConnection()->statement('COMMENT ON SCHEMA library IS '.$quoted);
    }

    private function requireTransaction(): void
    {
        if ($this->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Use php artisan library:migrate for transactional bookkeeping.');
        }
    }
}
