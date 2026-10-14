<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;

final class MigrateLibrary extends Command
{
    protected $signature = 'library:migrate';
    protected $description = 'Run Laravel migrations and history atomically without an extra table';

    public function handle(DatabaseManager $database, Migrator $migrator): int
    {
        $database->connection()->transaction(function () use ($database, $migrator): void {
            $database->select('SELECT pg_advisory_xact_lock(3145555, 2)');
            $repository = $migrator->getRepository();
            if (!$repository->repositoryExists()) {
                $repository->createRepository();
            }
            $migrator->setOutput($this->output)->run([database_path('migrations')]);
        });
        return self::SUCCESS;
    }
}
