<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListAssignment;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\OrderLineException;
use App\Modules\Orders\Exceptions\OrderTransitionException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderWriter;
use App\Support\Money;
use Illuminate\Support\Carbon;

/*
 * Building an order reaches across two module boundaries: the price comes
 * from Catalog through the Pricebook contract, and the product's details
 * through ProductCatalogue. Orders names neither module.
 */

function writer(): OrderWriter
{
    return app(OrderWriter::class);
}

/**
 * A product with a price on the house list.
 *
 * @param  array<string, mixed>  $productState
 */
function sellable(string $price = '312.00', array $productState = []): Product
{
    $unit = UnitOfMeasure::query()->firstOrCreate(['code' => 'CTN'], ['name' => 'Carton']);
    $product = Product::factory()->create([...$productState, 'unit_of_measure_id' => $unit->id]);

    $list = PriceList::query()->where('is_default', true)->first()
        ?? PriceList::factory()->create(['is_default' => true, 'currency' => 'ETB']);

    PriceListItem::factory()->pricedAt($price)->create([
        'price_list_id' => $list->id,
        'product_id' => $product->id,
    ]);

    return $product;
}

it('prices a line from the list in force', function () {
    $product = sellable('312.00');
    $order = writer()->startDraft(customerId: 1);

    $line = writer()->addLine($order, $product->id, 4);

    expect($line->unit_price_minor)->toBe(31200)
        ->and($line->line_total_minor)->toBe(124800)
        ->and($line->unitPrice()->toDecimal())->toBe('312.00')
        ->and($order->refresh()->total()->toDecimal())->toBe('1248.00');
});

it('prefers the price list attached to the customer', function () {
    $product = sellable('312.00');

    $wholesale = PriceList::factory()->create(['currency' => 'ETB']);
    PriceListItem::factory()->pricedAt('295.00')->create([
        'price_list_id' => $wholesale->id,
        'product_id' => $product->id,
    ]);
    PriceListAssignment::factory()->forCustomer(77)->create(['price_list_id' => $wholesale->id]);

    $order = writer()->startDraft(customerId: 77);
    $line = writer()->addLine($order, $product->id, 1);

    expect($line->unitPrice()->toDecimal())->toBe('295.00')
        ->and($line->price_list_id)->toBe($wholesale->id)
        ->and($order->refresh()->price_list_id)->toBe($wholesale->id);
});

it('copies the product onto the line so a rename cannot rewrite history', function () {
    $product = sellable('312.00', ['sku' => 'AMB-W-1000', 'name' => 'Ambo Mineral Water 1L']);
    $order = writer()->startDraft(customerId: 1);
    $line = writer()->addLine($order, $product->id, 2);

    $product->update(['name' => 'Ambo Sparkling 1L', 'sku' => 'AMB-S-1000']);

    expect($line->refresh()->product_name)->toBe('Ambo Mineral Water 1L')
        ->and($line->product_sku)->toBe('AMB-W-1000')
        ->and($line->unit_code)->toBe('CTN')
        ->and($line->product_id)->toBe($product->id);
});

it('adds to the existing line when the same product is ordered again', function () {
    $product = sellable('100.00');
    $order = writer()->startDraft(customerId: 1);

    writer()->addLine($order, $product->id, 3);
    $line = writer()->addLine($order, $product->id, 2);

    expect($order->refresh()->lines)->toHaveCount(1)
        ->and($line->quantity)->toBe(5)
        ->and($order->total()->toDecimal())->toBe('500.00');
});

it('keeps the total in step when a quantity changes', function () {
    $product = sellable('50.00');
    $order = writer()->startDraft(customerId: 1);
    $line = writer()->addLine($order, $product->id, 4);

    writer()->changeQuantity($order, $line, 10);

    expect($order->refresh()->total()->toDecimal())->toBe('500.00')
        ->and($line->refresh()->line_total_minor)->toBe(50000);
});

it('keeps the total in step when a line goes', function () {
    $first = sellable('50.00');
    $second = sellable('25.00');
    $order = writer()->startDraft(customerId: 1);

    writer()->addLine($order, $first->id, 2);
    $doomed = writer()->addLine($order, $second->id, 4);

    writer()->removeLine($order, $doomed);

    expect($order->refresh()->lines)->toHaveCount(1)
        ->and($order->total()->toDecimal())->toBe('100.00');
});

it('always agrees with the sum of its lines', function () {
    $a = sellable('12.35');
    $b = sellable('7.05');
    $order = writer()->startDraft(customerId: 1);

    writer()->addLine($order, $a->id, 7);
    writer()->addLine($order, $b->id, 13);

    $order->refresh();
    $sum = $order->lines->sum('line_total_minor');

    expect($order->total_minor)->toBe($sum)
        ->and($order->total()->toDecimal())->toBe(
            Money::ofMinor((int) $sum)->toDecimal()
        );
});

it('refuses a product nothing prices', function () {
    $unpriced = Product::factory()->create(['name' => 'Nobody prices this']);
    $order = writer()->startDraft(customerId: 1);

    writer()->addLine($order, $unpriced->id, 1);
})->throws(OrderLineException::class, 'No price list in force prices [Nobody prices this]');

it('refuses a product that is no longer sold', function () {
    $product = sellable('10.00', ['is_active' => false, 'name' => 'Discontinued']);
    $order = writer()->startDraft(customerId: 1);

    writer()->addLine($order, $product->id, 1);
})->throws(OrderLineException::class, 'is no longer sold');

it('refuses a product that does not exist', function () {
    writer()->addLine(writer()->startDraft(customerId: 1), 999_999, 1);
})->throws(OrderLineException::class, 'There is no product with id 999999.');

it('refuses a quantity below one', function (int $quantity) {
    $product = sellable();
    writer()->addLine(writer()->startDraft(customerId: 1), $product->id, $quantity);
})->with(['zero' => [0], 'negative' => [-3]])
    ->throws(OrderLineException::class, 'A line quantity must be at least 1');

it('refuses to touch the lines of an order that has left draft', function () {
    $product = sellable();
    $order = writer()->startDraft(customerId: 1);
    writer()->addLine($order, $product->id, 1);
    $order->submit();

    writer()->addLine($order, sellable('9.00')->id, 1);
})->throws(OrderTransitionException::class, 'Only a draft can be edited.');

it('prices as at the day the order was taken, not today', function () {
    $product = sellable('312.00');

    // A cheaper list that only came into force this week.
    $newer = PriceList::factory()->create(['is_default' => true, 'effective_from' => Carbon::today()]);
    PriceListItem::factory()->pricedAt('280.00')->create([
        'price_list_id' => $newer->id,
        'product_id' => $product->id,
    ]);

    $backdated = writer()->startDraft(customerId: 1, placedAt: Carbon::today()->subMonth());
    $line = writer()->addLine($backdated, $product->id, 1);

    expect($line->unitPrice()->toDecimal())->toBe('312.00');
});

it('numbers orders sequentially within the year', function () {
    $first = writer()->startDraft(customerId: 1, placedAt: Carbon::parse('2026-03-04'));
    $second = writer()->startDraft(customerId: 2, placedAt: Carbon::parse('2026-07-19'));
    $nextYear = writer()->startDraft(customerId: 3, placedAt: Carbon::parse('2027-01-02'));

    expect($first->reference)->toBe('SO-2026-00001')
        ->and($second->reference)->toBe('SO-2026-00002')
        ->and($nextYear->reference)->toBe('SO-2027-00001');
});

it('starts an order as a draft with nothing owed', function () {
    $order = writer()->startDraft(customerId: 5, salesRepId: 2, routeId: 3);

    expect($order->status)->toBe(OrderStatus::Draft)
        ->and($order->customer_id)->toBe(5)
        ->and($order->sales_rep_id)->toBe(2)
        ->and($order->route_id)->toBe(3)
        ->and($order->total()->isZero())->toBeTrue()
        ->and(Order::query()->count())->toBe(1);
});
