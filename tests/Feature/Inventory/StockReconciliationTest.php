<?php

declare(strict_types=1);

use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Enums\ReconciliationStatus;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReconciliation;
use App\Modules\Inventory\Models\StockReconciliationLine;
use App\Modules\Inventory\Services\StockLedger;
use Illuminate\Database\UniqueConstraintViolationException;

/*
 * End of day. The van is counted, the count is compared with the ledger, and
 * the difference is reported rather than quietly applied. Closing the
 * reconciliation is what writes the adjustments, and each one points back
 * here, so the trail runs from "we counted 11" to the row that settled it.
 */

/** @param  array<int, array{0: int, 1: int}>  $counts  product id to [expected, counted] */
function reconciliationFor(int $repId, array $counts): StockReconciliation
{
    $reconciliation = StockReconciliation::factory()->forRep($repId)->create();

    foreach ($counts as $productId => [$expected, $counted]) {
        StockReconciliationLine::factory()
            ->counted($productId, $expected, $counted)
            ->create(['stock_reconciliation_id' => $reconciliation->id]);
    }

    return $reconciliation->load('lines');
}

it('reports a variance without touching the ledger', function () {
    $reconciliation = reconciliationFor(repId(), [productId() => [12, 11]]);

    expect($reconciliation->hasVariance())->toBeTrue()
        ->and($reconciliation->variances())->toHaveCount(1)
        ->and($reconciliation->lines->first()?->variance())->toBe(-1)
        ->and($reconciliation->lines->first()?->isShort())->toBeTrue()
        ->and(StockMovement::query()->count())->toBe(0);
});

it('sees no variance when the count agrees', function () {
    $reconciliation = reconciliationFor(repId(), [productId() => [12, 12], 99 => [4, 4]]);

    expect($reconciliation->hasVariance())->toBeFalse()
        ->and($reconciliation->variances())->toBeEmpty();
});

it('writes an adjustment per variance when it closes', function () {
    $ledger = app(StockLedger::class);
    $ledger->receive(productId(), warehouseId(), 100);
    $ledger->loadVan(productId(), warehouseId(), repId(), 12);

    $reconciliation = reconciliationFor(repId(), [productId() => [12, 11]]);
    $reconciliation->close($ledger);

    $adjustment = StockMovement::query()->where('type', MovementType::Adjustment)->sole();

    expect($adjustment->quantity)->toBe(-1)
        ->and($adjustment->location_type)->toBe(LocationType::Van)
        ->and($adjustment->location_id)->toBe(repId())
        ->and($adjustment->reference_type)->toBe('reconciliation')
        ->and($adjustment->reference_id)->toBe($reconciliation->id)
        ->and($adjustment->notes)->toContain('ledger said 12, counted 11');
});

it('makes the ledger agree with the count', function () {
    $ledger = app(StockLedger::class);
    $ledger->receive(productId(), warehouseId(), 100);
    $ledger->loadVan(productId(), warehouseId(), repId(), 12);

    reconciliationFor(repId(), [productId() => [12, 11]])->close($ledger);

    expect($ledger->balance(productId(), LocationType::Van, repId()))->toBe(11);
});

it('handles a surplus as readily as a shortage', function () {
    $ledger = app(StockLedger::class);
    $ledger->receive(productId(), warehouseId(), 100);
    $ledger->loadVan(productId(), warehouseId(), repId(), 10);

    reconciliationFor(repId(), [productId() => [10, 13]])->close($ledger);

    expect($ledger->balance(productId(), LocationType::Van, repId()))->toBe(13)
        ->and(StockMovement::query()->where('type', MovementType::Adjustment)->sole()->quantity)->toBe(3);
});

it('writes nothing for the products that counted correctly', function () {
    $ledger = app(StockLedger::class);
    $ledger->receive(productId(), warehouseId(), 100);
    $ledger->receive(99, warehouseId(), 100);
    $ledger->loadVan(productId(), warehouseId(), repId(), 12);
    $ledger->loadVan(99, warehouseId(), repId(), 4);

    reconciliationFor(repId(), [productId() => [12, 11], 99 => [4, 4]])->close($ledger);

    expect(StockMovement::query()->where('type', MovementType::Adjustment)->count())->toBe(1);
});

it('closes only once', function () {
    $ledger = app(StockLedger::class);
    $reconciliation = reconciliationFor(repId(), [productId() => [1, 1]]);

    $reconciliation->close($ledger);
    $reconciliation->close($ledger);
})->throws(LogicException::class, 'already closed');

it('records when it was closed', function () {
    $ledger = app(StockLedger::class);
    $reconciliation = reconciliationFor(repId(), [productId() => [5, 5]]);

    expect($reconciliation->isOpen())->toBeTrue();

    $reconciliation->close($ledger);

    expect($reconciliation->status)->toBe(ReconciliationStatus::Closed)
        ->and($reconciliation->isOpen())->toBeFalse()
        ->and($reconciliation->closed_at)->not->toBeNull();
});

it('allows one count per rep per day', function () {
    StockReconciliation::factory()->forRep(repId())->create(['reconciled_on' => today()]);
    StockReconciliation::factory()->forRep(repId())->create(['reconciled_on' => today()]);
})->throws(UniqueConstraintViolationException::class);

it('lets a different rep count on the same day', function () {
    StockReconciliation::factory()->forRep(repId())->create(['reconciled_on' => today()]);
    StockReconciliation::factory()->forRep(8)->create(['reconciled_on' => today()]);

    expect(StockReconciliation::query()->count())->toBe(2);
});

it('leaves an adjustment that can be traced back to the count', function () {
    $ledger = app(StockLedger::class);
    $ledger->receive(productId(), warehouseId(), 50);
    $ledger->loadVan(productId(), warehouseId(), repId(), 20);

    $reconciliation = reconciliationFor(repId(), [productId() => [20, 18]]);
    $reconciliation->close($ledger);

    $traced = StockMovement::query()
        ->where('reference_type', 'reconciliation')
        ->where('reference_id', $reconciliation->id)
        ->get();

    expect($traced)->toHaveCount(1)
        ->and($traced->first()?->quantity)->toBe(-2);
});
