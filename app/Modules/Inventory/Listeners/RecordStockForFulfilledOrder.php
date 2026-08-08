<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Listeners;

use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedger;
use App\Support\Events\OrderFulfilled;

/**
 * Takes the goods out of stock when an order is fulfilled.
 *
 * Orders raises the event and knows nothing about this module; the stock side
 * of a fulfilment lives here. Where the goods leave from depends on how the
 * order was taken: a rep's order comes off their van, an office order out of
 * the default warehouse.
 */
final readonly class RecordStockForFulfilledOrder
{
    public function __construct(private StockLedger $ledger) {}

    public function handle(OrderFulfilled $event): void
    {
        [$locationType, $locationId] = $this->sourceFor($event->salesRepId);

        if ($locationId === null) {
            // Nothing to take stock from. An installation that has not set up
            // a warehouse yet can still take orders; it simply has no ledger.
            return;
        }

        foreach ($event->quantities as $productId => $quantity) {
            $this->ledger->sell(
                productId: $productId,
                locationType: $locationType,
                locationId: $locationId,
                quantity: $quantity,
                orderId: $event->orderId,
                on: $event->occurredAt,
            );
        }
    }

    /** @return array{0: LocationType, 1: int|null} */
    private function sourceFor(?int $salesRepId): array
    {
        if ($salesRepId !== null) {
            return [LocationType::Van, $salesRepId];
        }

        $warehouseId = Warehouse::query()
            ->active()
            ->orderByDesc('is_default')
            ->value('id');

        return [LocationType::Warehouse, $warehouseId === null ? null : (int) $warehouseId];
    }
}
