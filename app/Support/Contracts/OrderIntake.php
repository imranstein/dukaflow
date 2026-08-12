<?php

declare(strict_types=1);

namespace App\Support\Contracts;

use Illuminate\Support\Carbon;

/**
 * Sync's one way to turn a pushed order into a real one. Sync depends on
 * this and never names Orders, or OrderWriter, directly.
 *
 * See Docs/adr/0002-offline-sync-strategy.md §7.
 */
interface OrderIntake
{
    /**
     * @param  list<array{product_id: int, quantity: int, unit_price_minor: int, price_list_id: int|null}>  $lines
     *                                                                                                              The device's own line prices, from the pricebook it pulled
     *                                                                                                              when it captured them — not necessarily today's prices.
     * @return array{order_id: int, reference: string, has_price_variance: bool}
     */
    public function submit(
        int $customerId,
        ?int $salesRepId,
        ?int $routeId,
        Carbon $placedAt,
        string $currency,
        array $lines,
    ): array;
}
