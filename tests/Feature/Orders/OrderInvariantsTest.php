<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\OrderLineException;
use App\Modules\Orders\Exceptions\OrderTransitionException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderWriter;
use App\Support\Events\OrderFulfilled;
use Illuminate\Support\Facades\Event;

/*
 * Each of these covers a guard that could have been deleted with the suite
 * still green. A rule nothing tests is a rule that will be refactored away by
 * someone who cannot see why it is there.
 */

function orderableAt(string $price, string $currency = 'ETB'): Product
{
    $product = Product::factory()->create();
    $list = PriceList::factory()->create(['is_default' => true, 'currency' => $currency]);

    PriceListItem::factory()->pricedAt($price)->create([
        'price_list_id' => $list->id,
        'product_id' => $product->id,
    ]);

    return $product;
}

it('refuses to change a line on an order that has left draft', function (OrderStatus $status) {
    $writer = app(OrderWriter::class);
    $product = orderableAt('100.00');

    $order = $writer->startDraft(customerId: 1);
    $line = $writer->addLine($order, $product->id, 2);

    // Straight to the state under test, then try to touch the lines.
    $order->forceFill(['status' => $status])->save();

    $writer->changeQuantity($order->refresh(), $line, 9);
})->with([
    'submitted' => [OrderStatus::Submitted],
    'approved' => [OrderStatus::Approved],
    'fulfilled' => [OrderStatus::Fulfilled],
    'cancelled' => [OrderStatus::Cancelled],
])->throws(OrderTransitionException::class, 'Only a draft can be edited.');

it('refuses to remove a line from an order that has left draft', function (OrderStatus $status) {
    $writer = app(OrderWriter::class);
    $product = orderableAt('100.00');

    $order = $writer->startDraft(customerId: 1);
    $line = $writer->addLine($order, $product->id, 2);
    $order->forceFill(['status' => $status])->save();

    $writer->removeLine($order->refresh(), $line);
})->with([
    'submitted' => [OrderStatus::Submitted],
    'approved' => [OrderStatus::Approved],
    'fulfilled' => [OrderStatus::Fulfilled],
])->throws(OrderTransitionException::class, 'Only a draft can be edited.');

it('refuses a product priced in another currency', function () {
    $writer = app(OrderWriter::class);

    // A price list in dollars, against an order taken in birr. Without the
    // guard the dollar amount would be written onto the birr order as if the
    // numbers meant the same thing.
    $product = orderableAt('25.00', 'USD');
    $order = $writer->startDraft(customerId: 1, currency: 'ETB');

    $writer->addLine($order, $product->id, 1);
})->throws(OrderLineException::class, 'is priced in USD but the order is in ETB');

it('keeps the price list the first line was priced under', function () {
    $writer = app(OrderWriter::class);
    $order = $writer->startDraft(customerId: 1);

    $first = orderableAt('10.00');
    $firstList = PriceList::query()->where('is_default', true)->sole();
    $writer->addLine($order, $first->id, 1);

    expect($order->refresh()->price_list_id)->toBe($firstList->id);

    // A second product priced by a different list must not overwrite it:
    // Phase 3 revalidates an offline order against the list it names.
    $firstList->update(['is_default' => false]);
    $second = orderableAt('20.00');
    $writer->addLine($order, $second->id, 1);

    expect($order->refresh()->price_list_id)->toBe($firstList->id);
});

it('takes stock from the default warehouse when several are active', function () {
    $writer = app(OrderWriter::class);
    $ledger = app(StockLedger::class);

    $overflow = Warehouse::factory()->create(['name' => 'Overflow shed']);
    $main = Warehouse::factory()->default()->create(['name' => 'Kality depot']);

    $product = orderableAt('10.00');
    $ledger->receive($product->id, $main->id, 20);
    $ledger->receive($product->id, $overflow->id, 20);

    $order = $writer->startDraft(customerId: 1);
    $writer->addLine($order, $product->id, 5);
    $order->sales_rep_id = null;
    $order->save();
    $order->submit()->approve()->fulfil();

    expect($ledger->balance($product->id, LocationType::Warehouse, $main->id))->toBe(15)
        ->and($ledger->balance($product->id, LocationType::Warehouse, $overflow->id))->toBe(20);
});

it('does nothing rather than failing when no warehouse exists yet', function () {
    $writer = app(OrderWriter::class);
    $product = orderableAt('10.00');

    // A fresh install can take orders before anyone sets up a warehouse.
    $order = $writer->startDraft(customerId: 1);
    $writer->addLine($order, $product->id, 3);
    $order->sales_rep_id = null;
    $order->save();

    $order->submit()->approve()->fulfil();

    expect($order->fresh()?->status)->toBe(OrderStatus::Fulfilled)
        ->and(StockMovement::query()->count())->toBe(0);
});

it('carries the quantities on the fulfilment event', function () {
    Event::fake([OrderFulfilled::class]);
    $writer = app(OrderWriter::class);

    $a = orderableAt('10.00');
    $b = orderableAt('20.00');
    $order = $writer->startDraft(customerId: 1);
    $writer->addLine($order, $a->id, 3);
    $writer->addLine($order, $b->id, 7);
    $order->submit()->approve()->fulfil();

    Event::assertDispatched(
        OrderFulfilled::class,
        fn (OrderFulfilled $e): bool => $e->quantities === [$a->id => 3, $b->id => 7],
    );
});

it('agrees with the sum of its lines after every kind of edit', function () {
    $writer = app(OrderWriter::class);
    $order = $writer->startDraft(customerId: 1);

    $a = $writer->addLine($order, orderableAt('12.35')->id, 7);
    $writer->addLine($order, orderableAt('7.05')->id, 13);
    $writer->changeQuantity($order, $a, 2);
    $writer->addLine($order, $a->product_id, 3);
    $writer->removeLine($order, $a->refresh());

    $order->refresh();

    expect($order->total_minor)->toBe((int) $order->lines()->sum('line_total_minor'));
});

it('counts an order that has no lines as worth nothing', function () {
    $order = app(OrderWriter::class)->startDraft(customerId: 1);

    expect($order->total()->isZero())->toBeTrue()
        ->and(fn () => $order->submit())->toThrow(OrderTransitionException::class);
});

it('leaves a cancelled order out of what is owed', function () {
    $writer = app(OrderWriter::class);
    $order = $writer->startDraft(customerId: 1);
    $writer->addLine($order, orderableAt('100.00')->id, 5);
    $order->submit()->cancel('Outlet shut');

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Order::query()->open()->count())->toBe(0);
});
