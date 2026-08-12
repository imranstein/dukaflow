<?php

declare(strict_types=1);

namespace App\Modules\Distribution;

use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use App\Modules\Distribution\Models\VisitOutcome;
use App\Modules\Distribution\Models\VisitSchedule;
use App\Modules\Distribution\Support\DistributionDirectory;
use App\Modules\Distribution\Support\DistributionReps;
use App\Modules\Distribution\Support\DistributionSyncFeed;
use App\Modules\Distribution\Support\DistributionVisitIntake;
use App\Policies\BackOfficePolicy;
use App\Support\CompositeScopeDirectory;
use App\Support\CompositeSyncFeed;
use App\Support\Contracts\RepDirectory;
use App\Support\Contracts\VisitOutcomeIntake;
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
        VisitOutcome::class,
    ];

    public function register(): void
    {
        // Sync turns a pushed visit outcome into a real one through this
        // contract, never through VisitOutcome directly. See
        // Docs/adr/0002-offline-sync-strategy.md §7.
        $this->app->bind(VisitOutcomeIntake::class, DistributionVisitIntake::class);
        $this->app->bind(RepDirectory::class, DistributionReps::class);
    }

    public function boot(): void
    {
        // Lets other modules name outlets, routes and reps without depending
        // on this one. See App\Support\Contracts\ScopeDirectory.
        $this->app->make(CompositeScopeDirectory::class)->register(new DistributionDirectory);
        $this->app->make(CompositeSyncFeed::class)->register(new DistributionSyncFeed);

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        foreach (self::MODELS as $model) {
            Gate::policy($model, BackOfficePolicy::class);
        }
    }
}
