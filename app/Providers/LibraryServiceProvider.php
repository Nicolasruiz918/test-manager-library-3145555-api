<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Library\Adapters\Outbound\Persistence\SchemaMigrationRepository;
use App\Library\Application\Ports;
use App\Library\Adapters\Outbound\Persistence;
use App\Library\Adapters\Outbound\Runtime;
use App\Library\Adapters\Outbound\Security;

/** The single composition point for application ports and adapters. */
final class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend('migration.repository', static fn ($original, $app) => new SchemaMigrationRepository($app['db']));
        $this->app->singleton(Ports\BookRepository::class, fn ($app) => new Persistence\PgBookRepository($app['db']->connection()));
        $this->app->singleton(Ports\MemberRepository::class, fn ($app) => new Persistence\PgMemberRepository($app['db']->connection()));
        $this->app->singleton(Ports\UserRepository::class, fn ($app) => new Persistence\PgUserRepository($app['db']->connection()));
        $this->app->singleton(Ports\CategoryCatalog::class, fn ($app) => new Persistence\PgCategoryCatalog($app['db']->connection()));
        $this->app->singleton(Ports\Transaction::class, fn ($app) => new Persistence\PgTransaction($app['db']->connection()));
        $this->app->singleton(Ports\LoanRepository::class, fn ($app) => new Persistence\PgLoanRepository($app['db']->connection()));
        $this->app->singleton(Ports\LoanQueries::class, fn ($app) => new Persistence\PgLoanQueries($app['db']->connection(), $app->make(Ports\LoanRepository::class)));
        $this->app->singleton(Ports\ReportQuery::class, fn ($app) => new Persistence\PgReportQuery($app['db']->connection()));
        $this->app->singleton(Ports\IdentifierGenerator::class, Runtime\RandomIds::class);
        $this->app->singleton(Ports\Clock::class, Runtime\SystemClock::class);
        $this->app->singleton(Ports\PasswordHasher::class, Security\ArgonPasswords::class);
        $this->app->singleton(Security\JwtTokens::class, fn ($app) => new Security\JwtTokens((string) $app['config']['library.jwt_secret']));
        $this->app->alias(Security\JwtTokens::class, Ports\TokenIssuer::class);
        $this->app->alias(Security\JwtTokens::class, Ports\TokenVerifier::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([\App\Console\Commands\MigrateLibrary::class]);
        }
    }
}
