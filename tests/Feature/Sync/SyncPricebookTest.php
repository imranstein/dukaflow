<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Enums\PriceListScope;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListAssignment;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/*
 * A rep's whole route, pre-priced — Docs/adr/0002-offline-sync-strategy.md
 * §5. The device looks these up rather than resolving them itself.
 */

it('is empty for a rep with no route', function () {
    $user = User::factory()->rep()->create();
    SalesRep::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    getJson('/api/sync/pricebook?device_id='.Str::ulid())
        ->assertOk()
        ->assertJsonPath('prices', []);
});

it('prices every product for every active customer on the reps route', function () {
    $user = User::factory()->rep()->create();
    $rep = SalesRep::factory()->create(['user_id' => $user->id]);
    $route = Route::factory()->create(['sales_rep_id' => $rep->id]);
    $customer = Customer::factory()->create(['route_id' => $route->id]);
    Customer::factory()->inactive()->create(['route_id' => $route->id]);

    $list = PriceList::factory()->default()->create(['currency' => 'ETB']);
    $product = Product::factory()->create();
    PriceListItem::factory()->for($list, 'priceList')->pricedAt('42.50')->create(['product_id' => $product->id]);

    actingAs($user);

    $response = getJson('/api/sync/pricebook?device_id='.Str::ulid());

    $response->assertOk();
    $prices = $response->json('prices');

    expect($prices)->toHaveCount(1)
        ->and($prices[0]['customer_id'])->toBe($customer->id)
        ->and($prices[0]['product_id'])->toBe($product->id)
        ->and($prices[0]['unit_price_minor'])->toBe(4250)
        ->and($prices[0]['price_list_id'])->toBe($list->id);
});

it('prefers a customer-specific list over the default', function () {
    $user = User::factory()->rep()->create();
    $rep = SalesRep::factory()->create(['user_id' => $user->id]);
    $route = Route::factory()->create(['sales_rep_id' => $rep->id]);
    $customer = Customer::factory()->create(['route_id' => $route->id]);

    $product = Product::factory()->create();
    $defaultList = PriceList::factory()->default()->create(['currency' => 'ETB']);
    PriceListItem::factory()->for($defaultList, 'priceList')->pricedAt('50.00')->create(['product_id' => $product->id]);

    $customerList = PriceList::factory()->create(['currency' => 'ETB']);
    PriceListItem::factory()->for($customerList, 'priceList')->pricedAt('30.00')->create(['product_id' => $product->id]);
    PriceListAssignment::query()->create([
        'price_list_id' => $customerList->id,
        'scope' => PriceListScope::Customer,
        'scope_id' => $customer->id,
    ]);

    actingAs($user);

    $prices = getJson('/api/sync/pricebook?device_id='.Str::ulid())->json('prices');

    expect($prices)->toHaveCount(1)
        ->and($prices[0]['unit_price_minor'])->toBe(3000)
        ->and($prices[0]['price_list_id'])->toBe($customerList->id);
});
