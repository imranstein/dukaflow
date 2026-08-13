<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Support;

use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\VisitSchedule;
use App\Support\Contracts\SyncFeed;
use App\Support\SyncCursor;

/**
 * Distribution's answer to a device's pull: the outlets a rep calls on,
 * the routes they sit on, and which days they're due — scoped to that one
 * rep's own book, not the whole distributor's. See
 * Docs/adr/0002-offline-sync-strategy.md §4.
 *
 * A route reassigned away from a rep, or a customer hard-deleted, simply
 * stops appearing in that rep's future pulls rather than arriving once more
 * with an explicit "no longer yours" signal — idsInScope() below is what
 * tells a device to drop its stale copy instead. See
 * Docs/adr/0007-reconciling-stale-device-caches.md.
 */
final class DistributionSyncFeed implements SyncFeed
{
    /** @return list<string> */
    public function entityTypes(): array
    {
        return ['customer', 'route', 'visit_schedule'];
    }

    /** @return list<array{id: int, updated_at: string, data: array<string, mixed>}> */
    public function pull(string $entityType, ?SyncCursor $cursor, int $limit, ?int $salesRepId): array
    {
        // Fails closed: no rep resolved means no scope to answer for, not
        // "everyone's."
        if ($salesRepId === null) {
            return [];
        }

        return match ($entityType) {
            'customer' => $this->customers($cursor, $limit, $salesRepId),
            'route' => $this->routes($cursor, $limit, $salesRepId),
            'visit_schedule' => $this->visitSchedules($cursor, $limit, $salesRepId),
            default => [],
        };
    }

    /** @return list<array{id: int, updated_at: string, data: array<string, mixed>}> */
    private function customers(?SyncCursor $cursor, int $limit, int $salesRepId): array
    {
        $routeIds = $this->routeIdsFor($salesRepId);

        return SyncCursor::apply(Customer::query()->whereIn('route_id', $routeIds), $cursor)
            ->limit($limit)
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'updated_at' => $customer->updated_at?->toIso8601String() ?? '',
                'data' => [
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'outlet_type' => $customer->outlet_type->value,
                    'owner_name' => $customer->owner_name,
                    'phone' => $customer->phone,
                    'address' => $customer->address,
                    'latitude' => $customer->latitude,
                    'longitude' => $customer->longitude,
                    'route_id' => $customer->route_id,
                    // Deactivation flows down as an ordinary update. A hard
                    // delete does not — see idsInScope() below.
                    'is_active' => $customer->is_active,
                ],
            ])
            ->all();
    }

    /** @return list<array{id: int, updated_at: string, data: array<string, mixed>}> */
    private function routes(?SyncCursor $cursor, int $limit, int $salesRepId): array
    {
        return SyncCursor::apply(Route::query()->where('sales_rep_id', $salesRepId), $cursor)
            ->limit($limit)
            ->get()
            ->map(fn (Route $route): array => [
                'id' => $route->id,
                'updated_at' => $route->updated_at?->toIso8601String() ?? '',
                'data' => [
                    'code' => $route->code,
                    'name' => $route->name,
                    'sales_rep_id' => $route->sales_rep_id,
                    'is_active' => $route->is_active,
                ],
            ])
            ->all();
    }

    /** @return list<array{id: int, updated_at: string, data: array<string, mixed>}> */
    private function visitSchedules(?SyncCursor $cursor, int $limit, int $salesRepId): array
    {
        $customerIds = $this->customerIdsFor($salesRepId);

        return SyncCursor::apply(VisitSchedule::query()->whereIn('customer_id', $customerIds), $cursor)
            ->limit($limit)
            ->get()
            ->map(fn (VisitSchedule $schedule): array => [
                'id' => $schedule->id,
                'updated_at' => $schedule->updated_at?->toIso8601String() ?? '',
                'data' => [
                    'customer_id' => $schedule->customer_id,
                    'day_of_week' => $schedule->day_of_week->value,
                    'sequence' => $schedule->sequence,
                    'is_active' => $schedule->is_active,
                ],
            ])
            ->all();
    }

    /**
     * The complete current id set for one entity type and rep — see
     * Docs/adr/0007-reconciling-stale-device-caches.md. Not a delta: the
     * same scoping query pull() itself filters by, so a route reassigned
     * away from this rep or a hard-deleted customer simply isn't in it.
     *
     * @return list<int>
     */
    public function idsInScope(string $entityType, ?int $salesRepId): array
    {
        if ($salesRepId === null) {
            return [];
        }

        return match ($entityType) {
            'customer' => $this->customerIdsFor($salesRepId),
            'route' => $this->routeIdsFor($salesRepId),
            'visit_schedule' => VisitSchedule::query()->whereIn('customer_id', $this->customerIdsFor($salesRepId))->pluck('id')->all(),
            default => [],
        };
    }

    /** @return list<int> */
    private function routeIdsFor(int $salesRepId): array
    {
        return Route::query()->where('sales_rep_id', $salesRepId)->pluck('id')->all();
    }

    /** @return list<int> */
    private function customerIdsFor(int $salesRepId): array
    {
        return Customer::query()->whereIn('route_id', $this->routeIdsFor($salesRepId))->pluck('id')->all();
    }
}
