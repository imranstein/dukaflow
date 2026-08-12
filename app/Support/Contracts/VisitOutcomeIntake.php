<?php

declare(strict_types=1);

namespace App\Support\Contracts;

use Illuminate\Support\Carbon;

/**
 * Sync's one way to turn a pushed visit outcome into a real one. Sync
 * depends on this and never names Distribution, or VisitOutcome, directly.
 *
 * See Docs/adr/0002-offline-sync-strategy.md §7.
 */
interface VisitOutcomeIntake
{
    /** @return array{visit_outcome_id: int} */
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
    ): array;
}
