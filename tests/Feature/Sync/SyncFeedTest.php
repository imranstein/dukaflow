<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\CatalogSyncFeed;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\VisitSchedule;
use App\Modules\Distribution\Support\DistributionSyncFeed;
use App\Support\CompositeSyncFeed;
use App\Support\SyncCursor;

/*
 * The pull side of Docs/adr/0002-offline-sync-strategy.md §4 and §7. What
 * matters is the cursor surviving a same-second write, and the composite
 * routing to whichever module actually owns the entity type asked for.
 */

it('breaks a same-second tie by id rather than dropping a row', function () {
    $a = Product::factory()->create();
    $b = Product::factory()->create();

    $tie = now()->startOfSecond();
    Product::query()->whereIn('id', [$a->id, $b->id])->update(['updated_at' => $tie]);

    $rows = (new CatalogSyncFeed)->pull('product', new SyncCursor($tie, min($a->id, $b->id)), 50);

    expect(collect($rows)->pluck('id')->all())->toBe([max($a->id, $b->id)]);
});

it('pulls nothing before the beginning and everything from a null cursor', function () {
    Product::factory()->count(3)->create();

    $rows = (new CatalogSyncFeed)->pull('product', null, 50);

    expect($rows)->toHaveCount(3)
        ->and($rows[0]['data'])->toHaveKeys(['sku', 'name', 'unit', 'is_active']);
});

it('feeds every distribution entity type it claims', function () {
    $customer = Customer::factory()->create();
    $route = Route::factory()->create();
    VisitSchedule::factory()->create(['customer_id' => $customer->id]);

    $feed = new DistributionSyncFeed;

    expect($feed->entityTypes())->toBe(['customer', 'route', 'visit_schedule'])
        ->and($feed->pull('customer', null, 50))->toHaveCount(1)
        ->and($feed->pull('route', null, 50))->toHaveCount(1)
        ->and($feed->pull('visit_schedule', null, 50))->toHaveCount(1)
        ->and($feed->pull('nonsense', null, 50))->toBe([]);
});

it('routes the composite to whichever module registered the entity type', function () {
    $composite = new CompositeSyncFeed;
    $composite->register(new CatalogSyncFeed);
    $composite->register(new DistributionSyncFeed);

    expect($composite->handles('product'))->toBeTrue()
        ->and($composite->handles('customer'))->toBeTrue()
        ->and($composite->handles('nonsense'))->toBeFalse();

    Product::factory()->create();

    expect($composite->pull('product', null, 10))->toHaveCount(1)
        ->and($composite->pull('nonsense', null, 10))->toBe([]);
});
