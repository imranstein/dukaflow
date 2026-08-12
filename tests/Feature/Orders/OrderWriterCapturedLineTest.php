<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\OrderLineException;
use App\Modules\Orders\Exceptions\OrderTransitionException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderWriter;
use App\Support\Money;

/*
 * addCapturedLine() is the one place a synced order's line gets written, and
 * it exists for exactly one reason: unlike addLine(), it must never
 * re-resolve the price. Docs/adr/0002-offline-sync-strategy.md §5.
 */

it('writes the price it was given rather than resolving one', function () {
    $unit = UnitOfMeasure::factory()->create(['code' => 'CTN']);
    $product = Product::factory()->create(['unit_of_measure_id' => $unit->id]);
    $order = Order::factory()->create(['currency' => 'ETB']);

    // No price list assignment exists anywhere for this product, so a live
    // resolver would find nothing to charge. addCapturedLine does not ask it.
    $line = app(OrderWriter::class)->addCapturedLine(
        $order,
        $product->id,
        quantity: 4,
        price: Money::ofMinor(1250, 'ETB'),
        priceListId: 99,
    );

    expect($line->unit_price_minor)->toBe(1250)
        ->and($line->quantity)->toBe(4)
        ->and($line->line_total_minor)->toBe(5000)
        ->and($line->price_list_id)->toBe(99)
        ->and($order->fresh()?->total_minor)->toBe(5000);
});

it('refuses a second captured line for the same product rather than merging it', function () {
    $product = Product::factory()->create();
    $order = Order::factory()->create(['currency' => 'ETB']);
    $writer = app(OrderWriter::class);

    $writer->addCapturedLine($order, $product->id, 2, Money::ofMinor(1000, 'ETB'), null);

    expect(fn () => $writer->addCapturedLine($order, $product->id, 3, Money::ofMinor(1000, 'ETB'), null))
        ->toThrow(OrderLineException::class, 'is already a line on this order')
        ->and($order->lines()->count())->toBe(1);
});

it('still refuses a currency that does not match the order', function () {
    $product = Product::factory()->create();
    $order = Order::factory()->create(['currency' => 'ETB']);

    app(OrderWriter::class)->addCapturedLine($order, $product->id, 1, Money::ofMinor(100, 'USD'), null);
})->throws(OrderLineException::class);

it('still refuses to add to an order past draft', function () {
    $product = Product::factory()->create();
    $order = Order::factory()->status(OrderStatus::Submitted)->create();

    app(OrderWriter::class)->addCapturedLine($order, $product->id, 1, Money::ofMinor(100, $order->currency), null);
})->throws(OrderTransitionException::class);
