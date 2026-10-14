<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Library\Application\Ports\TokenIssuer;
use App\Library\Application\Services\ProvisionAdminService;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
final class InstallLibrary extends Command
{
    protected $signature = 'library:install';
    protected $description = 'Migrate the specified schema and provision the environment administrator';
    public function handle(DatabaseManager $database, ProvisionAdminService $provision, TokenIssuer $tokens, \App\Library\Application\Ports\Clock $clock): int
    {
        $username = (string) config('library.admin_username'); $password = (string) config('library.admin_password');
        if (trim($username) === '' || $password === '') { $this->error('ADMIN_USERNAME and ADMIN_PASSWORD are required.'); return self::FAILURE; }
        if ($this->call('library:migrate') !== self::SUCCESS) { return self::FAILURE; }
        try {
            $database->connection()->transaction(function () use ($database, $provision, $username, $password): void {
                $database->select('SELECT pg_advisory_xact_lock(3145555, 17)');
                $provision->execute($username, $password);
            });
        } catch (\LogicException $error) { $this->error($error->getMessage()); return self::FAILURE; }
        $this->info('Library schema and initial administrator are ready.');
        return self::SUCCESS;
    }
}
