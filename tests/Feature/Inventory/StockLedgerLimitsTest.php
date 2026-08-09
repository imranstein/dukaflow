<?php

declare(strict_types=1);

use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReconciliation;
use App\Modules\Inventory\Models\StockReconciliationLine;
use App\Modules\Inventory\Services\StockLedger;

/*
 * The edges of the ledger's guarantees, including the one place they stop.
 */

it('refuses a movement of nothing', function () {
    ledger()->record(
        productId: productId(),
        locationType: LocationType::Warehouse,
        locationId: warehouseId(),
        quantity: 0,
        type: MovementType::Adjustment,
    );
})->throws(InvalidArgumentException::class, 'A movement of zero says nothing');

it('refuses a negative count rather than flipping its sign', function (string $method) {
    // abs() used to turn a caller's sign error into a stock increase, so
    // receive(-50) added 50 instead of failing.
    match ($method) {
        'receive' => ledger()->receive(productId(), warehouseId(), -50),
        'loadVan' => ledger()->loadVan(productId(), warehouseId(), repId(), -50),
        'returnFromVan' => ledger()->returnFromVan(productId(), repId(), warehouseId(), -50),
        'sell' => ledger()->sell(productId(), LocationType::Van, repId(), -50, orderId: 1),
        default => throw new LogicException("No case for {$method}."),
    };
})->with(['receive', 'loadVan', 'returnFromVan', 'sell'])
    ->throws(InvalidArgumentException::class, 'needs a quantity of at least 1');

it('leaves the ledger untouched when a count is rejected', function () {
    ledger()->receive(productId(), warehouseId(), 10);

    try {
        ledger()->receive(productId(), warehouseId(), -5);
    } catch (InvalidArgumentException) {
        // expected
    }

    expect(ledger()->balance(productId(), LocationType::Warehouse, warehouseId()))->toBe(10);
});

it('guards the model against edits but not the query builder', function () {
    // Worth stating plainly: the append-only guard lives in the model's
    // events, so a mass update through the query builder goes straight past
    // it. Nothing in the application does that, and this test says what the
    // protection is actually worth rather than implying it is total.
    ledger()->receive(productId(), warehouseId(), 10);

    StockMovement::query()->where('product_id', productId())->update(['quantity' => 999]);

    expect(ledger()->balance(productId(), LocationType::Warehouse, warehouseId()))->toBe(999);
})->skip('Documents a known limit of model-event guards; see Docs/adr/0006-stock-ledger.md.');

it('closes a reconciliation against the ledger, not the count sheet', function () {
    $ledger = app(StockLedger::class);
    $ledger->receive(productId(), warehouseId(), 100);
    $ledger->loadVan(productId(), warehouseId(), repId(), 12);

    // The sheet was drawn up saying 12, then a sale landed before it closed.
    $reconciliation = StockReconciliation::factory()->forRep(repId())->create();
    StockReconciliationLine::factory()->counted(productId(), 12, 10)
        ->create(['stock_reconciliation_id' => $reconciliation->id]);

    $ledger->sell(productId(), LocationType::Van, repId(), 2, orderId: 1);

    $reconciliation->load('lines')->close($ledger);

    // The count said 10 are on the van, so the van holds 10. Trusting the
    // stale expected of 12 would have written a variance of -2 against a
    // balance already down to 10, leaving 8.
    expect($ledger->balance(productId(), LocationType::Van, repId()))->toBe(10);
});

it('will not apply the same count twice from two instances', function () {
    $ledger = app(StockLedger::class);
    $ledger->receive(productId(), warehouseId(), 100);
    $ledger->loadVan(productId(), warehouseId(), repId(), 12);

    $reconciliation = StockReconciliation::factory()->forRep(repId())->create();
    StockReconciliationLine::factory()->counted(productId(), 12, 11)
        ->create(['stock_reconciliation_id' => $reconciliation->id]);

    $first = StockReconciliation::query()->with('lines')->findOrFail($reconciliation->id);
    $second = StockReconciliation::query()->with('lines')->findOrFail($reconciliation->id);

    $first->close($ledger);

    expect(fn () => $second->close($ledger))->toThrow(LogicException::class, 'already closed');

    expect($ledger->balance(productId(), LocationType::Van, repId()))->toBe(11);
});
