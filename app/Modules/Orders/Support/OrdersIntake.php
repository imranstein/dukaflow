<?php

declare(strict_types=1);

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Services\OrderWriter;
use App\Support\Contracts\OrderIntake;
use App\Support\Contracts\Pricebook;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orders' side of the sync contract. Sync depends on OrderIntake and never
 * sees OrderWriter or Order directly — see
 * Docs/adr/0002-offline-sync-strategy.md §7.
 */
final readonly class OrdersIntake implements OrderIntake
{
    public function __construct(
        private OrderWriter $writer,
        private Pricebook $pricebook,
    ) {}

    public function submit(
        int $customerId,
        ?int $salesRepId,
        ?int $routeId,
        Carbon $placedAt,
        string $currency,
        array $lines,
    ): array {
        return DB::transaction(function () use ($customerId, $salesRepId, $routeId, $placedAt, $currency, $lines): array {
            $order = $this->writer->startDraft(
                customerId: $customerId,
                salesRepId: $salesRepId,
                routeId: $routeId,
                placedAt: $placedAt,
                currency: $currency,
            );

            $hasVariance = false;

            foreach ($lines as $line) {
                $this->writer->addCapturedLine(
                    order: $order,
                    productId: $line['product_id'],
                    quantity: $line['quantity'],
                    price: Money::ofMinor($line['unit_price_minor'], $currency),
                    priceListId: $line['price_list_id'],
                );

                if ($this->linePriceHasDrifted($line, $customerId, $routeId, $placedAt, $currency)) {
                    $hasVariance = true;
                }
            }

            if ($hasVariance) {
                $order->has_price_variance = true;
                $order->save();
            }

            // A field order is not a draft awaiting a decision — the rep
            // already made the sale. Submitting it here puts it straight
            // into the back office's approval queue, the same place a
            // manager keying one in by hand would send it.
            $order->submit();

            return [
                'order_id' => $order->id,
                'reference' => $order->reference,
                'has_price_variance' => $hasVariance,
            ];
        });
    }

    /**
     * @param  array{product_id: int, quantity: int, unit_price_minor: int, price_list_id: int|null}  $line
     */
    private function linePriceHasDrifted(
        array $line,
        int $customerId,
        ?int $routeId,
        Carbon $placedAt,
        string $currency,
    ): bool {
        // Resolved as of placedAt, not now: the question is whether the
        // list in force at the moment of the sale has since changed under
        // it (an item edited in place, or a different list taking over),
        // not whether time has passed since capture.
        $current = $this->pricebook->priceFor($line['product_id'], $customerId, $routeId, on: $placedAt);
        $currentPriceListId = $this->pricebook->priceListIdFor($line['product_id'], $customerId, $routeId, on: $placedAt);

        return $current === null
            || $current->minorUnits !== $line['unit_price_minor']
            || $current->currency !== $currency
            || $currentPriceListId !== $line['price_list_id'];
    }
}
