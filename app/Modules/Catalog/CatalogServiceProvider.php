<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Listeners\PurgeAssignmentsForDeletedScope;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListAssignment;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Catalog\Services\PriceResolver;
use App\Modules\Catalog\Support\CatalogDirectory;
use App\Policies\BackOfficePolicy;
use App\Support\CompositeScopeDirectory;
use App\Support\Contracts\Pricebook;
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

    public function register(): void
    {
        // Orders prices its lines through this contract rather than by
        // reaching for Catalog's resolver directly.
        $this->app->bind(Pricebook::class, PriceResolver::class);
    }

    public function boot(): void
    {
        $this->app->make(CompositeScopeDirectory::class)->register(new CatalogDirectory);

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        foreach (self::MODELS as $model) {
            Gate::policy($model, BackOfficePolicy::class);
        }

        // Price list assignments point at customers and routes without a
        // foreign key, so this stands in for the cascade the database cannot
        // give us. See Docs/adr/0001-module-boundaries.md.
        Event::listen(ScopeRecordDeleted::class, PurgeAssignmentsForDeletedScope::class);
    }
}
