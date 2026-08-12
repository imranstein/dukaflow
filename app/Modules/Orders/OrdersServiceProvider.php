<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Orders\Support\OrdersIntake;
use App\Policies\BackOfficePolicy;
use App\Support\Contracts\OrderIntake;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    /** @var list<class-string> */
    private const MODELS = [
        Order::class,
        OrderLine::class,
        OrderPayment::class,
    ];

    public function register(): void
    {
        // Sync turns a pushed order into a real one through this contract,
        // never through OrderWriter or Order directly. See
        // Docs/adr/0002-offline-sync-strategy.md §7.
        $this->app->bind(OrderIntake::class, OrdersIntake::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        foreach (self::MODELS as $model) {
            Gate::policy($model, BackOfficePolicy::class);
        }
    }
}
