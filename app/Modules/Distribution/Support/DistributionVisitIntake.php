<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Support;

use App\Modules\Distribution\Models\VisitOutcome;
use App\Support\Contracts\VisitOutcomeIntake;
use Illuminate\Support\Carbon;

/**
 * Distribution's side of the sync contract. See
 * Docs/adr/0002-offline-sync-strategy.md §7.
 */
final class DistributionVisitIntake implements VisitOutcomeIntake
{
    public function record(
        string $clientId,
        int $customerId,
        int $salesRepId,
        ?int $routeId,
        string $outcome,
        ?string $reason,
        ?int $orderId,
        ?string $orderReference,
        Carbon $occurredAt,
    ): array {
        $record = VisitOutcome::record([
            'client_id' => $clientId,
            'customer_id' => $customerId,
            'sales_rep_id' => $salesRepId,
            'route_id' => $routeId,
            'outcome' => $outcome,
            'reason' => $reason,
            'order_id' => $orderId,
            'order_reference' => $orderReference,
            'occurred_at' => $occurredAt,
            'received_at' => Carbon::now(),
        ]);

        return ['visit_outcome_id' => $record->id];
    }
}
