<?php

declare(strict_types=1);

namespace App\Modules\Distribution;

use Illuminate\Support\ServiceProvider;

class DistributionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
