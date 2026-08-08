<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListAssignment;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\PriceResolver;
use Illuminate\Support\Carbon;

/*
 * The rule under test: the narrowest price list that is in force wins. A list
 * attached to the customer beats one attached to their route, which beats the
 * house default. Where two lists sit at the same level, the newer one wins.
 */

function resolver(): PriceResolver
{
    return new PriceResolver;
}

/**
 * Creates a price list that carries one price for the given product.
 *
 * @param  array<string, mixed>  $state
 */
function listPricing(Product $product, string $price, array $state = []): PriceList
{
    $list = PriceList::factory()->create($state);

    PriceListItem::factory()
        ->pricedAt($price)
        ->create(['price_list_id' => $list->id, 'product_id' => $product->id]);

    return $list;
}

it('falls back to the default price list', function () {
    $product = Product::factory()->create();
    listPricing($product, '25.00', ['is_default' => true]);

    $price = resolver()->priceFor($product->id, customerId: 42, routeId: 7);

    expect($price?->toDecimal())->toBe('25.00');
});

it('prefers a route price list over the default', function () {
    $product = Product::factory()->create();
    listPricing($product, '25.00', ['is_default' => true]);
    $routeList = listPricing($product, '23.00');
    PriceListAssignment::factory()->forRoute(7)->create(['price_list_id' => $routeList->id]);

    $price = resolver()->priceFor($product->id, customerId: 42, routeId: 7);

    expect($price?->toDecimal())->toBe('23.00');
});

it('prefers a customer price list over both', function () {
    $product = Product::factory()->create();
    listPricing($product, '25.00', ['is_default' => true]);
    $routeList = listPricing($product, '23.00');
    $customerList = listPricing($product, '21.50');
    PriceListAssignment::factory()->forRoute(7)->create(['price_list_id' => $routeList->id]);
    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $customerList->id]);

    $price = resolver()->priceFor($product->id, customerId: 42, routeId: 7);

    expect($price?->toDecimal())->toBe('21.50');
});

it('ignores an assignment belonging to a different customer', function () {
    $product = Product::factory()->create();
    listPricing($product, '25.00', ['is_default' => true]);
    $otherCustomersList = listPricing($product, '10.00');
    PriceListAssignment::factory()->forCustomer(999)->create(['price_list_id' => $otherCustomersList->id]);

    $price = resolver()->priceFor($product->id, customerId: 42);

    expect($price?->toDecimal())->toBe('25.00');
});

it('ignores a price list that has expired', function () {
    $product = Product::factory()->create();
    listPricing($product, '25.00', ['is_default' => true]);
    $expired = listPricing($product, '19.00', [
        'effective_from' => Carbon::today()->subYear(),
        'effective_to' => Carbon::today()->subDay(),
    ]);
    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $expired->id]);

    $price = resolver()->priceFor($product->id, customerId: 42);

    expect($price?->toDecimal())->toBe('25.00');
});

it('ignores a price list that has not started yet', function () {
    $product = Product::factory()->create();
    listPricing($product, '25.00', ['is_default' => true]);
    $future = listPricing($product, '19.00', ['effective_from' => Carbon::today()->addWeek()]);
    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $future->id]);

    expect(resolver()->priceFor($product->id, customerId: 42)?->toDecimal())->toBe('25.00');

    // ...but it applies once the date arrives.
    $priceNextWeek = resolver()->priceFor($product->id, customerId: 42, on: Carbon::today()->addWeek());

    expect($priceNextWeek?->toDecimal())->toBe('19.00');
});

it('treats the effective dates as inclusive', function () {
    $product = Product::factory()->create();
    listPricing($product, '19.00', [
        'is_default' => true,
        'effective_from' => Carbon::today(),
        'effective_to' => Carbon::today()->addDays(2),
    ]);

    expect(resolver()->priceFor($product->id, on: Carbon::today()))->not->toBeNull()
        ->and(resolver()->priceFor($product->id, on: Carbon::today()->addDays(2)))->not->toBeNull()
        ->and(resolver()->priceFor($product->id, on: Carbon::today()->subDay()))->toBeNull()
        ->and(resolver()->priceFor($product->id, on: Carbon::today()->addDays(3)))->toBeNull();
});

it('ignores an inactive price list', function () {
    $product = Product::factory()->create();
    listPricing($product, '25.00', ['is_default' => true]);
    $inactive = listPricing($product, '5.00', ['is_active' => false]);
    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $inactive->id]);

    expect(resolver()->priceFor($product->id, customerId: 42)?->toDecimal())->toBe('25.00');
});

it('takes the most recently effective list when two apply equally', function () {
    $product = Product::factory()->create();
    $older = listPricing($product, '30.00', ['effective_from' => Carbon::today()->subMonths(6)]);
    $newer = listPricing($product, '27.00', ['effective_from' => Carbon::today()->subMonth()]);
    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $older->id]);
    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $newer->id]);

    expect(resolver()->priceFor($product->id, customerId: 42)?->toDecimal())->toBe('27.00');
});

it('does not confuse a route assignment for a customer one', function () {
    $product = Product::factory()->create();
    listPricing($product, '25.00', ['is_default' => true]);

    // Same id, different kind of thing. Dropping the scope filter from the
    // lookup would make this outlet inherit the route's pricing.
    $routeList = listPricing($product, '9.00');
    PriceListAssignment::factory()->forRoute(7)->create(['price_list_id' => $routeList->id]);

    expect(resolver()->priceFor($product->id, customerId: 7)?->toDecimal())->toBe('25.00')
        ->and(resolver()->priceFor($product->id, routeId: 7)?->toDecimal())->toBe('9.00');
});

it('does not confuse a customer assignment for a route one', function () {
    $product = Product::factory()->create();
    listPricing($product, '25.00', ['is_default' => true]);

    $customerList = listPricing($product, '11.00');
    PriceListAssignment::factory()->forCustomer(3)->create(['price_list_id' => $customerList->id]);

    expect(resolver()->priceFor($product->id, routeId: 3)?->toDecimal())->toBe('25.00')
        ->and(resolver()->priceFor($product->id, customerId: 3)?->toDecimal())->toBe('11.00');
});

it('settles a same-day tie by taking the list created later', function () {
    $product = Product::factory()->create();
    $sameDay = Carbon::today()->subMonth();

    $first = listPricing($product, '30.00', ['effective_from' => $sameDay]);
    $second = listPricing($product, '27.00', ['effective_from' => $sameDay]);

    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $first->id]);
    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $second->id]);

    // Both sit at the same precedence and start on the same day. Without an
    // explicit tie-break the winner would be whichever row the database
    // happened to return first, which is to say undefined.
    expect(resolver()->priceFor($product->id, customerId: 42)?->toDecimal())->toBe('27.00');
});

it('resolves the same price every time when lists tie', function () {
    $product = Product::factory()->create();
    $sameDay = Carbon::today()->subMonth();

    foreach (['30.00', '27.00', '28.50'] as $price) {
        $list = listPricing($product, $price, ['effective_from' => $sameDay]);
        PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $list->id]);
    }

    $answers = collect(range(1, 5))
        ->map(fn (): ?string => resolver()->priceFor($product->id, customerId: 42)?->toDecimal())
        ->unique()
        ->values()
        ->all();

    expect($answers)->toBe(['28.50']);
});

it('returns nothing when no list prices the product', function () {
    $product = Product::factory()->create();
    $unpriced = Product::factory()->create();
    listPricing($product, '25.00', ['is_default' => true]);

    expect(resolver()->priceFor($unpriced->id, customerId: 42))->toBeNull();
});

it('skips a more specific list that does not carry the product', function () {
    $product = Product::factory()->create();
    listPricing($product, '25.00', ['is_default' => true]);

    // The customer has their own list, but it prices something else entirely.
    $customerList = PriceList::factory()->create();
    PriceListItem::factory()->pricedAt('99.00')->create([
        'price_list_id' => $customerList->id,
        'product_id' => Product::factory()->create()->id,
    ]);
    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $customerList->id]);

    expect(resolver()->priceFor($product->id, customerId: 42)?->toDecimal())->toBe('25.00');
});

it('takes the currency from the price list', function () {
    $product = Product::factory()->create();
    listPricing($product, '25.00', ['is_default' => true, 'currency' => 'KES']);

    expect(resolver()->priceFor($product->id)?->currency)->toBe('KES');
});

it('reports which list a price came from', function () {
    $product = Product::factory()->create();
    listPricing($product, '25.00', ['is_default' => true]);
    $customerList = listPricing($product, '21.50');
    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $customerList->id]);

    $used = resolver()->priceListFor($product->id, customerId: 42);

    expect($used?->id)->toBe($customerList->id);
});
