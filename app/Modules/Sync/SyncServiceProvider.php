<?php

declare(strict_types=1);

namespace App\Modules\Sync;

use App\Modules\Sync\Models\SyncAuditLog;
use App\Modules\Sync\Models\SyncConflict;
use App\Modules\Sync\Models\SyncDevice;
use App\Policies\BackOfficePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class SyncServiceProvider extends ServiceProvider
{
    /**
     * Laravel discovers policies by naming convention from app/Models, which
     * does not reach a module, so each module registers its own.
     *
     * @var list<class-string>
     */
    private const MODELS = [
        SyncDevice::class,
        SyncAuditLog::class,
        SyncConflict::class,
    ];

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/sync.php');

        foreach (self::MODELS as $model) {
            Gate::policy($model, BackOfficePolicy::class);
        }
    }
}
