<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Library\Adapters\Outbound\Persistence\SchemaMigrationRepository;

/** The single composition point for application ports and adapters. */
final class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend('migration.repository', static fn ($original, $app) => new SchemaMigrationRepository($app['db']));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([\App\Console\Commands\MigrateLibrary::class]);
        }
    }
}
