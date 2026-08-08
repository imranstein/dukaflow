<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Contracts\ScopeDirectory;
use App\Support\NullScopeDirectory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The fallback. Distribution replaces this with a directory that can
        // actually name outlets and routes; without that module the app still
        // boots and the price list screens simply offer nothing to attach to.
        $this->app->bind(ScopeDirectory::class, NullScopeDirectory::class);
    }
}
