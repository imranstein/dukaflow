<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReconciliation;
use App\Modules\Inventory\Models\StockReconciliationLine;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Orders\Services\OrderWriter;
use Illuminate\Support\Carbon;

/*
 * A rep's whole day, across all four modules, which is the Phase 2 acceptance
 * criterion: load a van in the morning, sell down the round, count what is
 * left, and reconcile.
 *
 * Nothing here reaches across a module boundary directly. Orders prices
 * through a contract and announces its fulfilments; Inventory listens. This
 * test is the proof that the seams actually meet.
 */

it('runs a rep through a full day of van sales', function () {
    $ledger = app(StockLedger::class);
    $writer = app(OrderWriter::class);

    // The distributor: a depot, a rep on a beat, two outlets, two products
    // on the house price list.
    $depot = Warehouse::factory()->default()->create(['name' => 'Kality depot']);
    $rep = SalesRep::factory()->create(['name' => 'Dawit Tesfaye']);
    $beat = Route::factory()->create(['sales_rep_id' => $rep->id]);
    $kiosk = Customer::factory()->onRoute($beat)->create(['name' => 'Rwanda Street Kiosk']);
    $cafe = Customer::factory()->onRoute($beat)->create(['name' => 'Sunrise Cafe']);

    $water = Product::factory()->create(['sku' => 'AMB-W-1000', 'name' => 'Ambo Mineral Water 1L']);
    $cola = Product::factory()->create(['sku' => 'MOH-C-0300', 'name' => 'Moha Cola 300ml']);

    $list = PriceList::factory()->create(['is_default' => true, 'currency' => 'ETB']);
    PriceListItem::factory()->pricedAt('312.00')->create(['price_list_id' => $list->id, 'product_id' => $water->id]);
    PriceListItem::factory()->pricedAt('420.00')->create(['price_list_id' => $list->id, 'product_id' => $cola->id]);

    // Stock arrives at the depot.
    $ledger->receive($water->id, $depot->id, 200);
    $ledger->receive($cola->id, $depot->id, 100);

    // Morning: the van is loaded.
    $ledger->loadVan($water->id, $depot->id, $rep->id, 40);
    $ledger->loadVan($cola->id, $depot->id, $rep->id, 24);

    expect($ledger->balance($water->id, LocationType::Warehouse, $depot->id))->toBe(160)
        ->and($ledger->balance($water->id, LocationType::Van, $rep->id))->toBe(40);

    // First call: the kiosk takes water and cola, and pays cash.
    $first = $writer->startDraft($kiosk->id, salesRepId: $rep->id, routeId: $beat->id);
    $writer->addLine($first, $water->id, 10);
    $writer->addLine($first, $cola->id, 4);

    expect($first->refresh()->total()->toDecimal())->toBe('4800.00');

    $first->submit()->approve()->fulfil();

    OrderPayment::factory()->of('4800.00')->create(['order_id' => $first->id]);

    expect($first->refresh()->isSettled())->toBeTrue()
        ->and($ledger->balance($water->id, LocationType::Van, $rep->id))->toBe(30)
        ->and($ledger->balance($cola->id, LocationType::Van, $rep->id))->toBe(20);

    // Second call: the cafe takes water on credit.
    $second = $writer->startDraft($cafe->id, salesRepId: $rep->id, routeId: $beat->id);
    $writer->addLine($second, $water->id, 5);
    $second->submit()->approve()->fulfil();

    expect($ledger->balance($water->id, LocationType::Van, $rep->id))->toBe(25)
        ->and($second->refresh()->balance()->toDecimal())->toBe('1560.00')
        ->and($second->isSettled())->toBeFalse();

    // Evening: the ledger says 25 waters and 20 colas are on the van. The
    // count finds one water short — a bottle broke.
    $reconciliation = StockReconciliation::factory()->forRep($rep->id)->create();
    StockReconciliationLine::factory()->counted($water->id, 25, 24)
        ->create(['stock_reconciliation_id' => $reconciliation->id]);
    StockReconciliationLine::factory()->counted($cola->id, 20, 20)
        ->create(['stock_reconciliation_id' => $reconciliation->id]);

    $reconciliation->load('lines');

    expect($reconciliation->hasVariance())->toBeTrue()
        ->and($reconciliation->variances())->toHaveCount(1);

    $reconciliation->close($ledger);

    expect($ledger->balance($water->id, LocationType::Van, $rep->id))->toBe(24);

    // Whatever is left goes back to the depot.
    $ledger->returnFromVan($water->id, $rep->id, $depot->id, 24);
    $ledger->returnFromVan($cola->id, $rep->id, $depot->id, 20);

    expect($ledger->balances(LocationType::Van, $rep->id))->toBe([])
        ->and($ledger->balance($water->id, LocationType::Warehouse, $depot->id))->toBe(184)
        ->and($ledger->balance($cola->id, LocationType::Warehouse, $depot->id))->toBe(96);

    // The day's books: 200 waters arrived, 15 were sold, one broke, 184 back
    // in the depot. Every one of those is a row somebody can point at.
    expect(200 - 15 - 1)->toBe(184)
        ->and(StockMovement::query()->where('type', MovementType::Sale)->count())->toBe(3)
        ->and(StockMovement::query()->where('type', MovementType::Adjustment)->count())->toBe(1);
});

it('will not sell what the van is not carrying', function () {
    $ledger = app(StockLedger::class);
    $writer = app(OrderWriter::class);

    $depot = Warehouse::factory()->default()->create();
    $rep = SalesRep::factory()->create();
    $outlet = Customer::factory()->create();
    $product = Product::factory()->create();

    $list = PriceList::factory()->create(['is_default' => true, 'currency' => 'ETB']);
    PriceListItem::factory()->pricedAt('100.00')->create([
        'price_list_id' => $list->id,
        'product_id' => $product->id,
    ]);

    $ledger->receive($product->id, $depot->id, 50);
    $ledger->loadVan($product->id, $depot->id, $rep->id, 3);

    $order = $writer->startDraft($outlet->id, salesRepId: $rep->id);
    $writer->addLine($order, $product->id, 5);
    $order->submit()->approve();

    // The order is fine on paper; the van is three cases short.
    expect(fn () => $order->fulfil())
        ->toThrow(InsufficientStockException::class);

    // And it stays approved. An order marked fulfilled with no stock movement
    // behind it is a lie the ledger could never account for.
    expect($order->fresh()?->status)->toBe(OrderStatus::Approved)
        ->and($order->fresh()?->fulfilled_at)->toBeNull()
        ->and($ledger->balance($product->id, LocationType::Van, $rep->id))->toBe(3)
        ->and(StockMovement::query()->where('type', MovementType::Sale)->count())->toBe(0);
});

it('takes an office order out of the default warehouse', function () {
    $ledger = app(StockLedger::class);
    $writer = app(OrderWriter::class);

    Warehouse::factory()->create(['name' => 'Overflow shed']);
    $main = Warehouse::factory()->default()->create(['name' => 'Kality depot']);
    $outlet = Customer::factory()->create();
    $product = Product::factory()->create();

    $list = PriceList::factory()->create(['is_default' => true, 'currency' => 'ETB']);
    PriceListItem::factory()->pricedAt('100.00')->create([
        'price_list_id' => $list->id,
        'product_id' => $product->id,
    ]);

    $ledger->receive($product->id, $main->id, 20);

    // No rep: this was taken in the office, so it leaves from the warehouse.
    $order = $writer->startDraft($outlet->id);
    $order->sales_rep_id = null;
    $order->save();
    $writer->addLine($order, $product->id, 6);
    $order->submit()->approve()->fulfil();

    expect($ledger->balance($product->id, LocationType::Warehouse, $main->id))->toBe(14);
});

it('leaves the ledger alone until the goods actually go', function () {
    $ledger = app(StockLedger::class);
    $writer = app(OrderWriter::class);

    $depot = Warehouse::factory()->default()->create();
    $rep = SalesRep::factory()->create();
    $product = Product::factory()->create();

    $list = PriceList::factory()->create(['is_default' => true, 'currency' => 'ETB']);
    PriceListItem::factory()->pricedAt('100.00')->create([
        'price_list_id' => $list->id,
        'product_id' => $product->id,
    ]);

    $ledger->receive($product->id, $depot->id, 30);
    $ledger->loadVan($product->id, $depot->id, $rep->id, 10);

    $order = $writer->startDraft(Customer::factory()->create()->id, salesRepId: $rep->id);
    $writer->addLine($order, $product->id, 4);
    $order->submit()->approve();

    // Approved, not fulfilled: the stock has not moved yet.
    expect($ledger->balance($product->id, LocationType::Van, $rep->id))->toBe(10)
        ->and(StockMovement::query()->where('type', MovementType::Sale)->count())->toBe(0);
});

it('does not move stock for an order that was cancelled', function () {
    $ledger = app(StockLedger::class);
    $writer = app(OrderWriter::class);

    $depot = Warehouse::factory()->default()->create();
    $rep = SalesRep::factory()->create();
    $product = Product::factory()->create();

    $list = PriceList::factory()->create(['is_default' => true, 'currency' => 'ETB']);
    PriceListItem::factory()->pricedAt('100.00')->create([
        'price_list_id' => $list->id,
        'product_id' => $product->id,
    ]);

    $ledger->receive($product->id, $depot->id, 30);
    $ledger->loadVan($product->id, $depot->id, $rep->id, 10);

    $order = $writer->startDraft(Customer::factory()->create()->id, salesRepId: $rep->id);
    $writer->addLine($order, $product->id, 4);
    $order->submit()->cancel('Outlet was shut');

    expect($ledger->balance($product->id, LocationType::Van, $rep->id))->toBe(10)
        ->and(StockMovement::query()->where('type', MovementType::Sale)->count())->toBe(0);
});

it('prices the round from the day the order was taken', function () {
    $writer = app(OrderWriter::class);
    Warehouse::factory()->default()->create();

    $product = Product::factory()->create();
    $old = PriceList::factory()->create([
        'is_default' => true,
        'currency' => 'ETB',
        'effective_from' => Carbon::today()->subYear(),
        'effective_to' => Carbon::today()->subDay(),
    ]);
    $current = PriceList::factory()->create([
        'is_default' => true,
        'currency' => 'ETB',
        'effective_from' => Carbon::today(),
    ]);

    PriceListItem::factory()->pricedAt('280.00')->create(['price_list_id' => $old->id, 'product_id' => $product->id]);
    PriceListItem::factory()->pricedAt('312.00')->create(['price_list_id' => $current->id, 'product_id' => $product->id]);

    $today = $writer->startDraft(Customer::factory()->create()->id);
    $line = $writer->addLine($today, $product->id, 1);

    expect($line->unitPrice()->toDecimal())->toBe('312.00')
        ->and($today->refresh()->price_list_id)->toBe($current->id);
});
