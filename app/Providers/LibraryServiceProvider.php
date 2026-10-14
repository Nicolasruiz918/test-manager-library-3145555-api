<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/** The single composition point for application ports and adapters. */
final class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
