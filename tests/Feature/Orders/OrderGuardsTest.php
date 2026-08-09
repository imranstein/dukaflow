<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\OrderLineException;
use App\Modules\Orders\Exceptions\OrderTransitionException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Services\OrderWriter;
use App\Support\Money;
use Illuminate\Support\Carbon;

/*
 * Each of these pins a defect a review found by reading the code. They are
 * the cases where the guard was in the right place but watching the wrong
 * thing, which is the kind that survives a green suite.
 */

function priced(string $price = '100.00'): Product
{
    $product = Product::factory()->create();
    $list = PriceList::query()->where('is_default', true)->first()
        ?? PriceList::factory()->create(['is_default' => true, 'currency' => 'ETB']);

    PriceListItem::factory()->pricedAt($price)->create([
        'price_list_id' => $list->id,
        'product_id' => $product->id,
    ]);

    return $product;
}

it('normalises the currency an order is opened in', function (string $given) {
    $order = app(OrderWriter::class)->startDraft(customerId: 1, currency: $given);

    expect($order->currency)->toBe('ETB');
})->with(['lowercase' => ['etb'], 'mixed case' => ['Etb'], 'padded' => [' etb ']]);

it('prices a line on an order opened in lowercase', function () {
    // The order stored 'etb' and the price came back 'ETB', so every line was
    // rejected as a currency mismatch on an order in the same currency.
    $writer = app(OrderWriter::class);
    $product = priced('312.00');
    $order = $writer->startDraft(customerId: 1, currency: 'etb');

    $line = $writer->addLine($order, $product->id, 2);

    expect($line->unitPrice()->toDecimal())->toBe('312.00')
        ->and($order->refresh()->total()->toDecimal())->toBe('624.00');
});

it('will not let a line be moved between orders', function () {
    $writer = app(OrderWriter::class);
    $product = priced();

    $frozen = $writer->startDraft(customerId: 1);
    $line = $writer->addLine($frozen, $product->id, 10);
    $frozen->submit()->approve();

    $draft = $writer->startDraft(customerId: 2);

    // The guard used to read the draft's status and let the frozen order's
    // line be deleted, which is the editability check protecting the wrong
    // record entirely.
    expect(fn () => $writer->removeLine($draft, $line))
        ->toThrow(OrderLineException::class, 'is not on order');

    expect($line->fresh())->not->toBeNull()
        ->and($frozen->refresh()->total()->toDecimal())->toBe('1000.00');
});

it('will not let a quantity be changed through another order', function () {
    $writer = app(OrderWriter::class);
    $product = priced();

    $frozen = $writer->startDraft(customerId: 1);
    $line = $writer->addLine($frozen, $product->id, 4);
    $frozen->submit();

    $draft = $writer->startDraft(customerId: 2);

    expect(fn () => $writer->changeQuantity($draft, $line, 999))
        ->toThrow(OrderLineException::class, 'is not on order');

    expect($line->refresh()->quantity)->toBe(4);
});

it('leaves the order alone in memory when a fulfilment fails', function () {
    $writer = app(OrderWriter::class);
    Warehouse::factory()->default()->create();
    $product = priced();

    $order = $writer->startDraft(customerId: 1, salesRepId: 3);
    $writer->addLine($order, $product->id, 5);
    $order->submit()->approve();

    // The van has nothing on it, so the listener throws and the row rolls
    // back. The instance in hand must roll back with it.
    try {
        $order->fulfil();
    } catch (Throwable) {
        // expected
    }

    expect($order->status)->toBe(OrderStatus::Approved)
        ->and($order->fulfilled_at)->toBeNull()
        ->and($order->isDirty())->toBeFalse();

    // And the obvious next move still works.
    $order->cancel('Van was empty');

    expect($order->fresh()?->status)->toBe(OrderStatus::Cancelled);
});

it('does not leave a cancellation reason on an order it refused to cancel', function () {
    $order = Order::factory()->status(OrderStatus::Fulfilled)->create();
    OrderLine::factory()->create(['order_id' => $order->id]);

    try {
        $order->cancel('Changed their mind');
    } catch (OrderTransitionException) {
        // expected: a fulfilled order is final
    }

    // The reason used to be assigned before the guard ran, so it sat dirty on
    // the model and the next save would have written it.
    expect($order->cancellation_reason)->toBeNull();

    $order->recalculateTotal();

    expect($order->fresh()?->cancellation_reason)->toBeNull()
        ->and($order->fresh()?->status)->toBe(OrderStatus::Fulfilled);
});

it('keeps numbering orders past the padding width', function () {
    $writer = app(OrderWriter::class);
    $year = Carbon::parse('2026-05-05');

    // Sorting references as plain strings puts 'SO-2026-99999' above
    // 'SO-2026-100000', so the counter would reissue a number it had used.
    Order::factory()->create(['reference' => 'SO-2026-99999', 'placed_at' => $year]);
    $hundredThousand = $writer->startDraft(customerId: 1, placedAt: $year);

    expect($hundredThousand->reference)->toBe('SO-2026-100000');

    $next = $writer->startDraft(customerId: 2, placedAt: $year);

    expect($next->reference)->toBe('SO-2026-100001');
});

it('still numbers the ordinary case in sequence', function () {
    $writer = app(OrderWriter::class);
    $day = Carbon::parse('2026-05-05');

    $references = collect(range(1, 12))
        ->map(fn (int $i): string => $writer->startDraft(customerId: $i, placedAt: $day)->reference);

    expect($references->first())->toBe('SO-2026-00001')
        ->and($references->last())->toBe('SO-2026-00012')
        ->and($references->unique())->toHaveCount(12);
});

it('rejects a currency Money cannot read at the point the order is opened', function () {
    app(OrderWriter::class)->startDraft(customerId: 1, currency: 'BIRR');
})->throws(InvalidArgumentException::class);

it('keeps the stored total equal to the lines through every edit', function () {
    $writer = app(OrderWriter::class);
    $a = priced('12.35');
    $b = priced('7.05');

    $order = $writer->startDraft(customerId: 1);
    $lineA = $writer->addLine($order, $a->id, 7);
    $writer->addLine($order, $b->id, 13);
    $writer->changeQuantity($order, $lineA, 3);
    $writer->removeLine($order, $lineA);

    $order->refresh();

    expect($order->total_minor)->toBe((int) $order->lines()->sum('line_total_minor'))
        ->and($order->total())->toEqual(Money::ofMinor(13 * 705));
});
