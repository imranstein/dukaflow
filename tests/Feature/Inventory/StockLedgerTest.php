<?php

declare(strict_types=1);

use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockLedger;
use Illuminate\Support\Carbon;

/*
 * The rule the module exists to protect, from Docs/adr/0006-stock-ledger.md:
 * a movement may not take a balance below zero unless it is an adjustment.
 *
 * Stock is never a column. Every assertion here goes through the ledger,
 * because that is the only thing that knows what is where.
 */

function ledger(): StockLedger
{
    return app(StockLedger::class);
}

it('starts with nothing anywhere', function () {
    expect(ledger()->balance(productId(), LocationType::Warehouse, warehouseId()))->toBe(0)
        ->and(ledger()->balances(LocationType::Warehouse, warehouseId()))->toBe([]);
});

it('adds up every movement rather than storing a total', function () {
    ledger()->receive(productId(), warehouseId(), 100);
    ledger()->receive(productId(), warehouseId(), 50);

    expect(ledger()->balance(productId(), LocationType::Warehouse, warehouseId()))->toBe(150)
        ->and(StockMovement::query()->count())->toBe(2);
});

it('keeps each place separate', function () {
    ledger()->receive(productId(), warehouseId(), 100);
    ledger()->receive(productId(), 2, 30);

    expect(ledger()->balance(productId(), LocationType::Warehouse, warehouseId()))->toBe(100)
        ->and(ledger()->balance(productId(), LocationType::Warehouse, 2))->toBe(30)
        ->and(ledger()->balance(productId(), LocationType::Van, warehouseId()))->toBe(0);
});

it('will not let a sale take a balance below zero', function () {
    ledger()->receive(productId(), warehouseId(), 5);

    ledger()->sell(productId(), LocationType::Warehouse, warehouseId(), 6, orderId: 1);
})->throws(InsufficientStockException::class, 'has 5 in warehouse 1, which is not enough to take 6');

it('will not let a van load overdraw the warehouse', function () {
    ledger()->receive(productId(), warehouseId(), 10);

    ledger()->loadVan(productId(), warehouseId(), repId(), 11);
})->throws(InsufficientStockException::class);

it('allows a sale that empties the balance exactly', function () {
    ledger()->receive(productId(), warehouseId(), 5);
    ledger()->sell(productId(), LocationType::Warehouse, warehouseId(), 5, orderId: 1);

    expect(ledger()->balance(productId(), LocationType::Warehouse, warehouseId()))->toBe(0);
});

it('leaves nothing behind when a movement is refused', function () {
    ledger()->receive(productId(), warehouseId(), 3);

    try {
        ledger()->sell(productId(), LocationType::Warehouse, warehouseId(), 10, orderId: 1);
    } catch (InsufficientStockException) {
        // expected
    }

    expect(StockMovement::query()->count())->toBe(1)
        ->and(ledger()->balance(productId(), LocationType::Warehouse, warehouseId()))->toBe(3);
});

it('lets an adjustment take a balance negative, because reality sometimes has', function () {
    ledger()->receive(productId(), warehouseId(), 2);

    $movement = ledger()->adjust(
        productId(),
        LocationType::Warehouse,
        warehouseId(),
        -5,
        reason: 'Counted a pallet that was never there.',
    );

    expect(ledger()->balance(productId(), LocationType::Warehouse, warehouseId()))->toBe(-3)
        ->and($movement->type)->toBe(MovementType::Adjustment)
        ->and($movement->notes)->toBe('Counted a pallet that was never there.');
});

it('marks only adjustments as allowed to go negative', function () {
    expect(MovementType::Adjustment->mayGoNegative())->toBeTrue()
        ->and(MovementType::Sale->mayGoNegative())->toBeFalse()
        ->and(MovementType::VanLoad->mayGoNegative())->toBeFalse()
        ->and(MovementType::VanReturn->mayGoNegative())->toBeFalse()
        ->and(MovementType::Receipt->mayGoNegative())->toBeFalse();
});

it('moves stock onto a van as two movements so both places tell the truth', function () {
    ledger()->receive(productId(), warehouseId(), 100);

    ledger()->loadVan(productId(), warehouseId(), repId(), 40);

    expect(ledger()->balance(productId(), LocationType::Warehouse, warehouseId()))->toBe(60)
        ->and(ledger()->balance(productId(), LocationType::Van, repId()))->toBe(40)
        ->and(StockMovement::query()->where('type', MovementType::VanLoad)->count())->toBe(2);
});

it('brings the unsold stock back', function () {
    ledger()->receive(productId(), warehouseId(), 100);
    ledger()->loadVan(productId(), warehouseId(), repId(), 40);
    ledger()->sell(productId(), LocationType::Van, repId(), 25, orderId: 1);
    ledger()->returnFromVan(productId(), repId(), warehouseId(), 15);

    expect(ledger()->balance(productId(), LocationType::Van, repId()))->toBe(0)
        ->and(ledger()->balance(productId(), LocationType::Warehouse, warehouseId()))->toBe(75);
});

it('will not return more than the van is carrying', function () {
    ledger()->receive(productId(), warehouseId(), 50);
    ledger()->loadVan(productId(), warehouseId(), repId(), 10);

    ledger()->returnFromVan(productId(), repId(), warehouseId(), 11);
})->throws(InsufficientStockException::class);

it('names the order a sale came from', function () {
    ledger()->receive(productId(), warehouseId(), 10);
    $sale = ledger()->sell(productId(), LocationType::Warehouse, warehouseId(), 4, orderId: 99);

    expect($sale->reference_type)->toBe('order')
        ->and($sale->reference_id)->toBe(99)
        ->and($sale->quantity)->toBe(-4);
});

it('reports everything on a van in one go', function () {
    ledger()->receive(productId(), warehouseId(), 100);
    ledger()->receive(99, warehouseId(), 100);
    ledger()->receive(123, warehouseId(), 100);

    ledger()->loadVan(productId(), warehouseId(), repId(), 10);
    ledger()->loadVan(99, warehouseId(), repId(), 5);
    ledger()->loadVan(123, warehouseId(), repId(), 8);
    ledger()->sell(123, LocationType::Van, repId(), 8, orderId: 1);

    // The one sold out entirely is not carried, so it is not listed.
    expect(ledger()->balances(LocationType::Van, repId()))->toBe([productId() => 10, 99 => 5]);
});

it('refuses to let a movement be edited', function () {
    $movement = ledger()->receive(productId(), warehouseId(), 10);

    $movement->update(['quantity' => 1000]);
})->throws(LogicException::class, 'append-only');

it('refuses to let a movement be deleted', function () {
    $movement = ledger()->receive(productId(), warehouseId(), 10);

    $movement->delete();
})->throws(LogicException::class, 'append-only');

it('keeps the balance intact after a failed edit', function () {
    $movement = ledger()->receive(productId(), warehouseId(), 10);

    try {
        $movement->update(['quantity' => 1000]);
    } catch (LogicException) {
        // expected
    }

    expect(ledger()->balance(productId(), LocationType::Warehouse, warehouseId()))->toBe(10);
});

it('records when a movement happened, not just when it was written', function () {
    $yesterday = Carbon::yesterday();
    $movement = ledger()->receive(productId(), warehouseId(), 10, on: $yesterday);

    expect($movement->occurred_at->toDateString())->toBe($yesterday->toDateString());
});

it('treats a sale from an empty van as impossible', function () {
    ledger()->sell(productId(), LocationType::Van, repId(), 1, orderId: 1);
})->throws(InsufficientStockException::class, 'has 0 in van 7');
