<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only way stock moves.
 *
 * Everything that changes the ledger comes through here, because the rule the
 * whole module exists to protect is checked here: a movement may not take a
 * balance below zero unless it is an adjustment.
 *
 * See Docs/adr/0006-stock-ledger.md.
 */
final class StockLedger
{
    /** What is at a place right now. */
    public function balance(int $productId, LocationType $locationType, int $locationId): int
    {
        return (int) StockMovement::query()
            ->at($locationType, $locationId)
            ->forProduct($productId)
            ->sum('quantity');
    }

    /**
     * Every product with a non-zero balance at a place.
     *
     * @return array<int, int> product id to quantity
     */
    public function balances(LocationType $locationType, int $locationId): array
    {
        /** @var array<int, int> $balances */
        $balances = StockMovement::query()
            ->at($locationType, $locationId)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as total')
            ->pluck('total', 'product_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->reject(fn (int $total): bool => $total === 0)
            ->all();

        return $balances;
    }

    /**
     * Writes one movement, refusing to overdraw.
     *
     * The check and the insert share a transaction, and the existing rows for
     * this product and place are locked while it runs, so two reps cannot
     * both sell the last case. That lock is coarse — it serialises writes for
     * one product at one location — which is the right trade here. If it ever
     * costs too much, the fix is a per-location balance row to lock instead.
     */
    public function record(
        int $productId,
        LocationType $locationType,
        int $locationId,
        int $quantity,
        MovementType $type,
        ?Carbon $occurredAt = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
    ): StockMovement {
        if ($quantity === 0) {
            throw new InvalidArgumentException(
                'A movement of zero says nothing. The ledger is evidence; do not pad it.'
            );
        }

        return DB::transaction(function () use (
            $productId, $locationType, $locationId, $quantity, $type,
            $occurredAt, $referenceType, $referenceId, $notes
        ): StockMovement {
            if ($quantity < 0 && ! $type->mayGoNegative()) {
                $available = (int) StockMovement::query()
                    ->at($locationType, $locationId)
                    ->forProduct($productId)
                    ->lockForUpdate()
                    ->sum('quantity');

                if ($available + $quantity < 0) {
                    throw InsufficientStockException::forProduct(
                        $productId,
                        $locationType,
                        $locationId,
                        $available,
                        abs($quantity),
                    );
                }
            }

            return StockMovement::query()->create([
                'product_id' => $productId,
                'location_type' => $locationType,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'occurred_at' => $occurredAt ?? Carbon::now(),
                'notes' => $notes,
            ]);
        });
    }

    /**
     * These four take a plain count, not a signed delta, and each decides the
     * direction itself. abs() used to hide a caller's sign error: passing -50
     * to receive() added 50 rather than failing.
     */
    private function assertCountable(int $quantity): int
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException(
                "A movement needs a quantity of at least 1, not {$quantity}. "
                .'Use adjust() to take stock away.'
            );
        }

        return $quantity;
    }

    /** Stock arriving from a supplier. */
    public function receive(int $productId, int $warehouseId, int $quantity, ?Carbon $on = null): StockMovement
    {
        return $this->record(
            productId: $productId,
            locationType: LocationType::Warehouse,
            locationId: $warehouseId,
            quantity: $this->assertCountable($quantity),
            type: MovementType::Receipt,
            occurredAt: $on,
        );
    }

    /**
     * Morning van load: out of the warehouse and onto the rep, as two
     * movements so both balances tell the truth.
     *
     * @return array{out: StockMovement, in: StockMovement}
     */
    public function loadVan(
        int $productId,
        int $warehouseId,
        int $salesRepId,
        int $quantity,
        ?Carbon $on = null,
    ): array {
        $quantity = $this->assertCountable($quantity);

        return DB::transaction(fn (): array => [
            'out' => $this->record(
                productId: $productId,
                locationType: LocationType::Warehouse,
                locationId: $warehouseId,
                quantity: -$quantity,
                type: MovementType::VanLoad,
                occurredAt: $on,
                referenceType: 'sales_rep',
                referenceId: $salesRepId,
            ),
            'in' => $this->record(
                productId: $productId,
                locationType: LocationType::Van,
                locationId: $salesRepId,
                quantity: $quantity,
                type: MovementType::VanLoad,
                occurredAt: $on,
                referenceType: 'warehouse',
                referenceId: $warehouseId,
            ),
        ]);
    }

    /**
     * Evening: whatever did not sell goes back.
     *
     * @return array{out: StockMovement, in: StockMovement}
     */
    public function returnFromVan(
        int $productId,
        int $salesRepId,
        int $warehouseId,
        int $quantity,
        ?Carbon $on = null,
    ): array {
        $quantity = $this->assertCountable($quantity);

        return DB::transaction(fn (): array => [
            'out' => $this->record(
                productId: $productId,
                locationType: LocationType::Van,
                locationId: $salesRepId,
                quantity: -$quantity,
                type: MovementType::VanReturn,
                occurredAt: $on,
                referenceType: 'warehouse',
                referenceId: $warehouseId,
            ),
            'in' => $this->record(
                productId: $productId,
                locationType: LocationType::Warehouse,
                locationId: $warehouseId,
                quantity: $quantity,
                type: MovementType::VanReturn,
                occurredAt: $on,
                referenceType: 'sales_rep',
                referenceId: $salesRepId,
            ),
        ]);
    }

    /** Goods leaving against an order. */
    public function sell(
        int $productId,
        LocationType $locationType,
        int $locationId,
        int $quantity,
        int $orderId,
        ?Carbon $on = null,
    ): StockMovement {
        return $this->record(
            productId: $productId,
            locationType: $locationType,
            locationId: $locationId,
            quantity: -$this->assertCountable($quantity),
            type: MovementType::Sale,
            occurredAt: $on,
            referenceType: 'order',
            referenceId: $orderId,
        );
    }

    /**
     * The books were wrong. This is the only movement allowed to leave a
     * balance below zero, and it always says who decided that.
     */
    public function adjust(
        int $productId,
        LocationType $locationType,
        int $locationId,
        int $quantity,
        string $reason,
        ?Carbon $on = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): StockMovement {
        return $this->record(
            productId: $productId,
            locationType: $locationType,
            locationId: $locationId,
            quantity: $quantity,
            type: MovementType::Adjustment,
            occurredAt: $on,
            referenceType: $referenceType,
            referenceId: $referenceId,
            notes: $reason,
        );
    }
}
