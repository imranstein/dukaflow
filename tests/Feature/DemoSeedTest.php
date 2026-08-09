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
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReconciliation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Models\OrderPayment;
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

it('seeds a week of trading so the order screens are not empty', function () {
    seed(DatabaseSeeder::class);

    expect(Order::query()->count())->toBeGreaterThan(0)
        ->and(OrderLine::query()->count())->toBeGreaterThan(0)
        ->and(OrderPayment::query()->count())->toBeGreaterThan(0)
        ->and(StockMovement::query()->count())->toBeGreaterThan(0)
        ->and(Warehouse::query()->count())->toBe(2)
        ->and(StockReconciliation::query()->count())->toBe(1);
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

    $counts = fn (): array => [
        'products' => Product::query()->count(),
        'customers' => Customer::query()->count(),
        'assignments' => PriceListAssignment::query()->count(),
        'users' => User::query()->count(),

        // The trading data too. Counting only the Phase 1 tables meant the
        // guard in TradingDemoSeeder could be deleted with this still green,
        // and a second seed would have stacked another week of orders.
        'orders' => Order::query()->count(),
        'order lines' => OrderLine::query()->count(),
        'payments' => OrderPayment::query()->count(),
        'movements' => StockMovement::query()->count(),
        'warehouses' => Warehouse::query()->count(),
        'reconciliations' => StockReconciliation::query()->count(),
    ];

    $before = $counts();

    seed(DatabaseSeeder::class);

    expect($counts())->toBe($before);
});
