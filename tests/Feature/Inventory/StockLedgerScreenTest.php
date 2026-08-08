<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\SalesRep;
use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Modules\Inventory\Filament\Resources\StockMovements\StockMovementResource;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedger;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * The ledger's rule lives in StockLedger, so the screen must not offer a way
 * around it. A generated create form would have written a row straight
 * through the model and skipped the balance check entirely.
 */

beforeEach(function (): void {
    actingAs(User::factory()->admin()->create());
});

it('offers no way to create or edit a movement by hand', function () {
    expect(StockMovementResource::canCreate())->toBeFalse()
        ->and(StockMovementResource::canEdit(new StockMovement))->toBeFalse()
        ->and(StockMovementResource::canDelete(new StockMovement))->toBeFalse()
        ->and(array_keys(StockMovementResource::getPages()))->toBe(['index']);
});

it('records a receipt through the ledger', function () {
    $product = Product::factory()->create();
    $depot = Warehouse::factory()->default()->create();

    Livewire::test(ListStockMovements::class)
        ->callAction('receive', data: [
            'product_id' => $product->id,
            'warehouse_id' => $depot->id,
            'quantity' => 60,
            'occurred_at' => today()->toDateString(),
        ])
        ->assertHasNoActionErrors();

    expect(app(StockLedger::class)->balance($product->id, LocationType::Warehouse, $depot->id))->toBe(60)
        ->and(StockMovement::query()->sole()->type)->toBe(MovementType::Receipt);
});

it('loads a van through the ledger, as two movements', function () {
    $product = Product::factory()->create();
    $depot = Warehouse::factory()->default()->create();
    $rep = SalesRep::factory()->create();

    app(StockLedger::class)->receive($product->id, $depot->id, 100);

    Livewire::test(ListStockMovements::class)
        ->callAction('loadVan', data: [
            'product_id' => $product->id,
            'warehouse_id' => $depot->id,
            'sales_rep_id' => $rep->id,
            'quantity' => 25,
            'occurred_at' => today()->toDateString(),
        ])
        ->assertHasNoActionErrors();

    $ledger = app(StockLedger::class);

    expect($ledger->balance($product->id, LocationType::Warehouse, $depot->id))->toBe(75)
        ->and($ledger->balance($product->id, LocationType::Van, $rep->id))->toBe(25);
});

it('will not let the screen overdraw a warehouse', function () {
    $product = Product::factory()->create();
    $depot = Warehouse::factory()->default()->create();
    $rep = SalesRep::factory()->create();

    app(StockLedger::class)->receive($product->id, $depot->id, 5);

    // The invariant holds through the UI exactly as it does in the service.
    expect(fn () => Livewire::test(ListStockMovements::class)
        ->callAction('loadVan', data: [
            'product_id' => $product->id,
            'warehouse_id' => $depot->id,
            'sales_rep_id' => $rep->id,
            'quantity' => 50,
            'occurred_at' => today()->toDateString(),
        ]))
        ->toThrow(InsufficientStockException::class);

    expect(app(StockLedger::class)->balance($product->id, LocationType::Warehouse, $depot->id))->toBe(5);
});

it('writes an adjustment with the reason attached', function () {
    $product = Product::factory()->create();
    $depot = Warehouse::factory()->default()->create();

    Livewire::test(ListStockMovements::class)
        ->callAction('adjust', data: [
            'product_id' => $product->id,
            'location_type' => LocationType::Warehouse->value,
            'warehouse_id' => $depot->id,
            'quantity' => -3,
            'reason' => 'Three cases crushed by the forklift.',
            'occurred_at' => today()->toDateString(),
        ])
        ->assertHasNoActionErrors();

    $movement = StockMovement::query()->sole();

    expect($movement->type)->toBe(MovementType::Adjustment)
        ->and($movement->quantity)->toBe(-3)
        ->and($movement->notes)->toBe('Three cases crushed by the forklift.')
        ->and(app(StockLedger::class)->balance($product->id, LocationType::Warehouse, $depot->id))->toBe(-3);
});

it('insists an adjustment says why', function () {
    $product = Product::factory()->create();
    $depot = Warehouse::factory()->default()->create();

    Livewire::test(ListStockMovements::class)
        ->callAction('adjust', data: [
            'product_id' => $product->id,
            'location_type' => LocationType::Warehouse->value,
            'warehouse_id' => $depot->id,
            'quantity' => -3,
            'reason' => '',
            'occurred_at' => today()->toDateString(),
        ])
        ->assertHasActionErrors(['reason']);

    expect(StockMovement::query()->count())->toBe(0);
});
