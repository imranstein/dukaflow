<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Support;

use App\Modules\Distribution\Models\SalesRep;
use App\Support\Contracts\RepDirectory;

final class DistributionReps implements RepDirectory
{
    public function repIdForUser(int $userId): ?int
    {
        return SalesRep::query()->where('user_id', $userId)->value('id');
    }
}
