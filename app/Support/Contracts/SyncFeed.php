<?php

declare(strict_types=1);

namespace App\Support\Contracts;

use App\Support\SyncCursor;

/**
 * One module's answer to "what changed for a device to pull." Distribution
 * and Catalog each register one into CompositeSyncFeed; Sync depends on the
 * composite and never names either module. Same shape as ScopeDirectory /
 * CompositeScopeDirectory. See Docs/adr/0002-offline-sync-strategy.md §7.
 */
interface SyncFeed
{
    /**
     * Entity type names this feed can answer for, e.g. 'product', 'customer'.
     *
     * @return list<string>
     */
    public function entityTypes(): array;

    /**
     * Up to $limit rows changed after $cursor, oldest first, each already
     * shaped for a device to store as-is.
     *
     * $salesRepId scopes the answer to what that rep may see — a device
     * pulling customers, routes or visit schedules gets its own rep's book,
     * not the whole distributor's. A feed with nothing rep-specific to say
     * (Catalog's products, shared distributor-wide) ignores it.
     *
     * @return list<array{id: int, updated_at: string, data: array<string, mixed>}>
     */
    public function pull(string $entityType, ?SyncCursor $cursor, int $limit, ?int $salesRepId): array;
}
