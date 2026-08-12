<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\CompositeScopeDirectory;
use App\Support\CompositeSyncFeed;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Contracts\SyncFeed;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // A singleton, because each module registers what it can name into
        // the same instance during boot. Resolving a fresh one per injection
        // would hand out empty directories.
        $this->app->singleton(CompositeScopeDirectory::class);
        $this->app->alias(CompositeScopeDirectory::class, ScopeDirectory::class);

        // Same shape, for what a device may pull. See
        // Docs/adr/0002-offline-sync-strategy.md §7.
        $this->app->singleton(CompositeSyncFeed::class);
        $this->app->alias(CompositeSyncFeed::class, SyncFeed::class);
    }
}
