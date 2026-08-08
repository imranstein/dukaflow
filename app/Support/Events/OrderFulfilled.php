<?php

declare(strict_types=1);

namespace App\Support\Events;

use Illuminate\Support\Carbon;

/**
 * Goods have left against an order.
 *
 * Inventory has to write stock movements when this happens, and Orders is not
 * allowed to know Inventory exists. So the fact is announced in primitives and
 * whoever cares listens. Same shape as ScopeRecordDeleted, same reason.
 */
final readonly class OrderFulfilled
{
    /**
     * @param  array<int, int>  $quantities  product id to quantity sold
     */
    public function __construct(
        public int $orderId,
        public string $reference,
        public ?int $salesRepId,
        public Carbon $occurredAt,
        public array $quantities,
    ) {}
}
