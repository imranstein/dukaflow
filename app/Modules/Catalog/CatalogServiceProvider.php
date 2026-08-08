<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Listeners\PurgeAssignmentsForDeletedScope;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListAssignment;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Policies\BackOfficePolicy;
use App\Support\Events\ScopeRecordDeleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    /**
     * Laravel discovers policies by naming convention from app/Models, which
     * does not reach a module, so each module registers its own.
     *
     * @var list<class-string>
     */
    private const MODELS = [
        Product::class,
        UnitOfMeasure::class,
        PriceList::class,
        PriceListItem::class,
        PriceListAssignment::class,
    ];

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        foreach (self::MODELS as $model) {
            Gate::policy($model, BackOfficePolicy::class);
        }

        // Price list assignments point at customers and routes without a
        // foreign key, so this stands in for the cascade the database cannot
        // give us. See docs/adr/0001-module-boundaries.md.
        Event::listen(ScopeRecordDeleted::class, PurgeAssignmentsForDeletedScope::class);
    }
}
