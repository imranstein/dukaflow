<?php

declare(strict_types=1);

namespace App\Support\Contracts;

/**
 * Which sales rep an authenticated user is, if any. Sync's HTTP layer uses
 * this to resolve who is pushing from the session rather than trusting
 * anything the request body claims to be. See
 * Docs/adr/0002-offline-sync-strategy.md §8.
 */
interface RepDirectory
{
    public function repIdForUser(int $userId): ?int;

    /**
     * Every route this rep covers. Sync's pull uses this to scope customer,
     * route and visit-schedule data to the rep asking rather than handing
     * every device the whole distributor's outlet list. See
     * Docs/adr/0002-offline-sync-strategy.md §4.
     *
     * @return list<int>
     */
    public function routeIdsForRep(int $salesRepId): array;

    /**
     * Whether this customer sits on one of this rep's own routes. Sync's
     * push uses this so a device can only place an order or record a visit
     * outcome for an outlet its rep actually covers.
     */
    public function ownsCustomer(int $salesRepId, int $customerId): bool;
}
