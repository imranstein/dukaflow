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
 * the routes they sit on, and which days they're due. See
 * Docs/adr/0002-offline-sync-strategy.md §4.
 */
final class DistributionSyncFeed implements SyncFeed
{
    /** @return list<string> */
    public function entityTypes(): array
    {
        return ['customer', 'route', 'visit_schedule'];
    }

    /** @return list<array{id: int, updated_at: string, data: array<string, mixed>}> */
    public function pull(string $entityType, ?SyncCursor $cursor, int $limit): array
    {
        return match ($entityType) {
            'customer' => $this->customers($cursor, $limit),
            'route' => $this->routes($cursor, $limit),
            'visit_schedule' => $this->visitSchedules($cursor, $limit),
            default => [],
        };
    }

    /** @return list<array{id: int, updated_at: string, data: array<string, mixed>}> */
    private function customers(?SyncCursor $cursor, int $limit): array
    {
        return SyncCursor::apply(Customer::query(), $cursor)
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
    private function routes(?SyncCursor $cursor, int $limit): array
    {
        return SyncCursor::apply(Route::query(), $cursor)
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
    private function visitSchedules(?SyncCursor $cursor, int $limit): array
    {
        return SyncCursor::apply(VisitSchedule::query(), $cursor)
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
}
