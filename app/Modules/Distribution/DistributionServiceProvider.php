<?php

declare(strict_types=1);

namespace App\Modules\Distribution;

use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use App\Modules\Distribution\Models\VisitSchedule;
use App\Modules\Distribution\Support\DistributionDirectory;
use App\Policies\BackOfficePolicy;
use App\Support\Contracts\ScopeDirectory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class DistributionServiceProvider extends ServiceProvider
{
    /**
     * Laravel discovers policies by naming convention from app/Models, which
     * does not reach a module, so each module registers its own.
     *
     * @var list<class-string>
     */
    private const MODELS = [
        Customer::class,
        Route::class,
        SalesRep::class,
        VisitSchedule::class,
    ];

    public function register(): void
    {
        // Lets other modules name outlets and routes without depending on this
        // one. See App\Support\Contracts\ScopeDirectory.
        $this->app->bind(ScopeDirectory::class, DistributionDirectory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        foreach (self::MODELS as $model) {
            Gate::policy($model, BackOfficePolicy::class);
        }
    }
}
