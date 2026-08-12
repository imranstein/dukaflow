<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Support;

use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use App\Support\Contracts\RepDirectory;

final class DistributionReps implements RepDirectory
{
    public function repIdForUser(int $userId): ?int
    {
        return SalesRep::query()->where('user_id', $userId)->value('id');
    }

    /** @return list<int> */
    public function routeIdsForRep(int $salesRepId): array
    {
        return Route::query()->where('sales_rep_id', $salesRepId)->pluck('id')->all();
    }

    public function ownsCustomer(int $salesRepId, int $customerId): bool
    {
        return Customer::query()
            ->whereKey($customerId)
            ->whereHas('route', fn ($query) => $query->where('sales_rep_id', $salesRepId))
            ->exists();
    }
}
