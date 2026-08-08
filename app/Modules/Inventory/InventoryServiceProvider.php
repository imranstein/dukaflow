<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use App\Modules\Inventory\Listeners\RecordStockForFulfilledOrder;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReconciliation;
use App\Modules\Inventory\Models\StockReconciliationLine;
use App\Modules\Inventory\Models\Warehouse;
use App\Policies\BackOfficePolicy;
use App\Support\Events\OrderFulfilled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    /** @var list<class-string> */
    private const MODELS = [
        Warehouse::class,
        StockMovement::class,
        StockReconciliation::class,
        StockReconciliationLine::class,
    ];

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        foreach (self::MODELS as $model) {
            Gate::policy($model, BackOfficePolicy::class);
        }

        // Orders announces a fulfilment; taking the goods out of stock is
        // this module's business, and Orders does not know it exists.
        Event::listen(OrderFulfilled::class, RecordStockForFulfilledOrder::class);
    }
}
