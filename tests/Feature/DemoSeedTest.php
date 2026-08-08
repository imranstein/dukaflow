<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListAssignment;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Catalog\Services\PriceResolver;
use App\Modules\Distribution\Enums\OutletType;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use Database\Seeders\DatabaseSeeder;

use function Pest\Laravel\seed;

/*
 * The demo dataset is a Phase 1 acceptance criterion and the thing the live
 * demo resets to every night, so it is a feature and gets tested like one.
 * Renaming a column or a seeder constant should break here, not on the demo.
 */

it('seeds a distributor you can actually look at', function () {
    seed(DatabaseSeeder::class);

    expect(Product::query()->count())->toBeGreaterThan(10)
        ->and(UnitOfMeasure::query()->count())->toBeGreaterThan(0)
        ->and(PriceList::query()->count())->toBe(2)
        ->and(Customer::query()->count())->toBeGreaterThan(10)
        ->and(Route::query()->count())->toBe(4)
        ->and(SalesRep::query()->count())->toBe(4);
});

it('gives every product a selling unit and a price', function () {
    seed(DatabaseSeeder::class);

    expect(Product::query()->whereNull('unit_of_measure_id')->count())->toBe(0)
        ->and(Product::query()->doesntHave('priceListItems')->count())->toBe(0);
});

it('puts every outlet on a route with at least one visit day', function () {
    seed(DatabaseSeeder::class);

    expect(Customer::query()->whereNull('route_id')->count())->toBe(0)
        ->and(Customer::query()->doesntHave('visitSchedules')->count())->toBe(0)
        ->and(Customer::query()->whereNull('latitude')->count())->toBe(0);
});

it('creates one login per role', function () {
    seed(DatabaseSeeder::class);

    foreach (UserRole::cases() as $role) {
        expect(User::query()->where('role', $role)->count())->toBe(1);
    }
});

it('shows the resolver choosing between two lists', function () {
    seed(DatabaseSeeder::class);

    $wholesaler = Customer::query()->where('outlet_type', OutletType::Wholesaler)->firstOrFail();
    $kiosk = Customer::query()->where('outlet_type', OutletType::Kiosk)->firstOrFail();
    $product = Product::query()->where('sku', 'AMB-W-1000')->firstOrFail();

    $resolver = new PriceResolver;
    $wholesalePrice = $resolver->priceFor($product->id, customerId: $wholesaler->id);
    $tradePrice = $resolver->priceFor($product->id, customerId: $kiosk->id);

    // The whole reason the demo carries two lists: a wholesaler pays less.
    expect($wholesalePrice?->toDecimal())->toBe('295.00')
        ->and($tradePrice?->toDecimal())->toBe('312.00')
        ->and($wholesalePrice?->minorUnits)->toBeLessThan($tradePrice?->minorUnits);
});

it('can be run twice without duplicating anything', function () {
    seed(DatabaseSeeder::class);

    $before = [
        Product::query()->count(),
        Customer::query()->count(),
        PriceListAssignment::query()->count(),
        User::query()->count(),
    ];

    seed(DatabaseSeeder::class);

    expect([
        Product::query()->count(),
        Customer::query()->count(),
        PriceListAssignment::query()->count(),
        User::query()->count(),
    ])->toBe($before);
});
