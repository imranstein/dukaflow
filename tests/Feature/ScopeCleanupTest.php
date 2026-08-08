<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\PriceListScope;
use App\Modules\Catalog\Models\PriceListAssignment;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Support\Events\ScopeRecordDeleted;
use Illuminate\Support\Facades\Event;

/*
 * Price list assignments point at outlets and routes by bare id, with no
 * foreign key, because the reference crosses a module boundary the database
 * cannot see. This event is what stands in for the missing cascade. Without
 * it the rows linger, and an id handed out again later inherits pricing that
 * was meant for somebody else.
 */

it('drops the assignments belonging to a deleted outlet', function () {
    $outlet = Customer::factory()->create();
    $survivor = Customer::factory()->create();

    PriceListAssignment::factory()->forCustomer($outlet->id)->create();
    PriceListAssignment::factory()->forCustomer($survivor->id)->create();

    $outlet->delete();

    expect(PriceListAssignment::query()->count())->toBe(1)
        ->and(PriceListAssignment::query()->sole()->scope_id)->toBe($survivor->id);
});

it('drops the assignments belonging to a deleted route', function () {
    $route = Route::factory()->create();
    PriceListAssignment::factory()->forRoute($route->id)->create();

    $route->delete();

    expect(PriceListAssignment::query()->count())->toBe(0);
});

it('leaves a route assignment alone when an outlet with the same id goes', function () {
    $outlet = Customer::factory()->create();
    $route = Route::factory()->create();

    // Ids are handed out per table, so an outlet and a route can share one.
    PriceListAssignment::factory()->forRoute($route->id)->create();

    $outlet->delete();

    expect(PriceListAssignment::query()->sole()->scope)->toBe(PriceListScope::Route);
});

it('announces the deletion in primitives', function () {
    Event::fake([ScopeRecordDeleted::class]);

    $outlet = Customer::factory()->create();
    $outlet->delete();

    Event::assertDispatched(
        ScopeRecordDeleted::class,
        fn (ScopeRecordDeleted $event): bool => $event->scope === 'customer' && $event->id === $outlet->id,
    );
});

it('says nothing when nothing is deleted', function () {
    Event::fake([ScopeRecordDeleted::class]);

    Customer::factory()->create()->update(['name' => 'Renamed']);

    Event::assertNotDispatched(ScopeRecordDeleted::class);
});
