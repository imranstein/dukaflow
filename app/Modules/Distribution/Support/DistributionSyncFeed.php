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
 * Known gap, accepted for v1: a route reassigned away from a rep simply
 * stops appearing in that rep's future pulls, rather than arriving once
 * more with an explicit "no longer yours" signal. A device already holding
 * it locally keeps a stale copy until told otherwise by some other means —
 * the same shape of gap ADR-002 §4 already accepts for a hard-deleted
 * customer, and acceptable at the same scale for the same reason.
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
                    // Deactivation is the deletion a device sees, per ADR-002
                    // §4; a hard-deleted customer is a rare back-office
                    // cleanup this feed does not promise to reconcile.
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
        $routeIds = $this->routeIdsFor($salesRepId);
        $customerIds = Customer::query()->whereIn('route_id', $routeIds)->pluck('id');

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

    /** @return list<int> */
    private function routeIdsFor(int $salesRepId): array
    {
        return Route::query()->where('sales_rep_id', $salesRepId)->pluck('id')->all();
    }
}
