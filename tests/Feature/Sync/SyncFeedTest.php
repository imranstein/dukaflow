<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\CatalogSyncFeed;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use App\Modules\Distribution\Models\VisitSchedule;
use App\Modules\Distribution\Support\DistributionSyncFeed;
use App\Support\CompositeSyncFeed;
use App\Support\SyncCursor;

/*
 * The pull side of Docs/adr/0002-offline-sync-strategy.md §4 and §7. What
 * matters is the cursor surviving a same-second write, the composite
 * routing to whichever module actually owns the entity type asked for, and
 * — the thing a strict review found missing — one rep never seeing
 * another rep's book.
 */

it('breaks a same-second tie by id rather than dropping a row', function () {
    $a = Product::factory()->create();
    $b = Product::factory()->create();

    $tie = now()->startOfSecond();
    Product::query()->whereIn('id', [$a->id, $b->id])->update(['updated_at' => $tie]);

    $rows = (new CatalogSyncFeed)->pull('product', new SyncCursor($tie, min($a->id, $b->id)), 50, null);

    expect(collect($rows)->pluck('id')->all())->toBe([max($a->id, $b->id)]);
});

it('pulls nothing before the beginning and everything from a null cursor', function () {
    Product::factory()->count(3)->create();

    $rows = (new CatalogSyncFeed)->pull('product', null, 50, null);

    expect($rows)->toHaveCount(3)
        ->and($rows[0]['data'])->toHaveKeys(['sku', 'name', 'unit', 'is_active']);
});

it('feeds every distribution entity type, scoped to the asking rep', function () {
    $rep = SalesRep::factory()->create();
    $route = Route::factory()->create(['sales_rep_id' => $rep->id]);
    $customer = Customer::factory()->create(['route_id' => $route->id]);
    VisitSchedule::factory()->create(['customer_id' => $customer->id]);

    $feed = new DistributionSyncFeed;

    expect($feed->entityTypes())->toBe(['customer', 'route', 'visit_schedule'])
        ->and($feed->pull('customer', null, 50, $rep->id))->toHaveCount(1)
        ->and($feed->pull('route', null, 50, $rep->id))->toHaveCount(1)
        ->and($feed->pull('visit_schedule', null, 50, $rep->id))->toHaveCount(1)
        ->and($feed->pull('nonsense', null, 50, $rep->id))->toBe([]);
});

it('never hands one rep another reps customers, routes or visit schedules', function () {
    $me = SalesRep::factory()->create();
    $them = SalesRep::factory()->create();
    $mine = Route::factory()->create(['sales_rep_id' => $me->id]);
    $theirs = Route::factory()->create(['sales_rep_id' => $them->id]);
    $myCustomer = Customer::factory()->create(['route_id' => $mine->id]);
    $theirCustomer = Customer::factory()->create(['route_id' => $theirs->id]);
    VisitSchedule::factory()->create(['customer_id' => $myCustomer->id]);
    VisitSchedule::factory()->create(['customer_id' => $theirCustomer->id]);

    $feed = new DistributionSyncFeed;

    expect(collect($feed->pull('customer', null, 50, $me->id))->pluck('id')->all())->toBe([$myCustomer->id])
        ->and(collect($feed->pull('route', null, 50, $me->id))->pluck('id')->all())->toBe([$mine->id])
        ->and(collect($feed->pull('visit_schedule', null, 50, $me->id))->pluck('data.customer_id')->all())->toBe([$myCustomer->id]);
});

it('fails closed rather than open when no rep is known', function () {
    $rep = SalesRep::factory()->create();
    Route::factory()->create(['sales_rep_id' => $rep->id]);
    Customer::factory()->create();

    $feed = new DistributionSyncFeed;

    expect($feed->pull('customer', null, 50, null))->toBe([])
        ->and($feed->pull('route', null, 50, null))->toBe([])
        ->and($feed->pull('visit_schedule', null, 50, null))->toBe([]);
});

it('routes the composite to whichever module registered the entity type', function () {
    $composite = new CompositeSyncFeed;
    $composite->register(new CatalogSyncFeed);
    $composite->register(new DistributionSyncFeed);

    expect($composite->handles('product'))->toBeTrue()
        ->and($composite->handles('customer'))->toBeTrue()
        ->and($composite->handles('nonsense'))->toBeFalse();

    Product::factory()->create();

    expect($composite->pull('product', null, 10, null))->toHaveCount(1)
        ->and($composite->pull('nonsense', null, 10, null))->toBe([]);
});
