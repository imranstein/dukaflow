<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Contracts\OrderIntake;
use Illuminate\Support\Str;

/*
 * Sync's one entry point for turning a pushed order into a real one. Prices
 * agreeing or not is the whole point of the contract — see
 * Docs/adr/0002-offline-sync-strategy.md §5.
 */

it('submits a captured order and puts it straight into the approval queue', function () {
    $customer = Customer::factory()->create();
    $list = PriceList::factory()->default()->create(['currency' => 'ETB']);
    $product = Product::factory()->create();
    PriceListItem::factory()->for($list, 'priceList')->pricedAt('50.00')->create(['product_id' => $product->id]);

    $clientId = (string) Str::ulid();

    $result = app(OrderIntake::class)->submit(
        clientId: $clientId,
        customerId: $customer->id,
        salesRepId: 7,
        routeId: null,
        placedAt: now(),
        currency: 'ETB',
        lines: [
            ['product_id' => $product->id, 'quantity' => 3, 'unit_price_minor' => 5000, 'price_list_id' => $list->id],
        ],
    );

    $order = Order::query()->findOrFail($result['order_id']);

    expect($result['has_price_variance'])->toBeFalse()
        ->and($order->status)->toBe(OrderStatus::Submitted)
        ->and($order->total_minor)->toBe(15000)
        ->and($order->has_price_variance)->toBeFalse()
        // The identity a sync endpoint dedupes on lives on the row itself,
        // not only in the audit log — Docs/adr/0003-id-strategy.md.
        ->and($order->client_id)->toBe($clientId);
});

it('flags an order whose captured price disagrees with the pricebook now', function () {
    $customer = Customer::factory()->create();
    $list = PriceList::factory()->default()->create(['currency' => 'ETB']);
    $product = Product::factory()->create();
    PriceListItem::factory()->for($list, 'priceList')->pricedAt('50.00')->create(['product_id' => $product->id]);

    // The rep captured this at 45.00 — an admin has since edited the item.
    $result = app(OrderIntake::class)->submit(
        clientId: (string) Str::ulid(),
        customerId: $customer->id,
        salesRepId: 7,
        routeId: null,
        placedAt: now(),
        currency: 'ETB',
        lines: [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price_minor' => 4500, 'price_list_id' => $list->id],
        ],
    );

    $order = Order::query()->findOrFail($result['order_id']);

    // Flagged, but the rep's price is what the order keeps — the sale
    // already happened at 45.00, and repricing it now would be a lie.
    expect($result['has_price_variance'])->toBeTrue()
        ->and($order->has_price_variance)->toBeTrue()
        ->and($order->lines()->first()?->unit_price_minor)->toBe(4500);
});

it('flags a variance when nothing prices the product any more', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();

    $result = app(OrderIntake::class)->submit(
        clientId: (string) Str::ulid(),
        customerId: $customer->id,
        salesRepId: 7,
        routeId: null,
        placedAt: now(),
        currency: 'ETB',
        lines: [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price_minor' => 4500, 'price_list_id' => 999],
        ],
    );

    expect($result['has_price_variance'])->toBeTrue();
});
