<?php

declare(strict_types=1);

namespace App\Modules\Sync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sync\Enums\SyncDirection;
use App\Modules\Sync\Enums\SyncStatus;
use App\Modules\Sync\Models\SyncAuditLog;
use App\Modules\Sync\Models\SyncDevice;
use App\Modules\Sync\Services\RepPricebook;
use App\Support\Contracts\RepDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SyncPricebookController extends Controller
{
    use ResolvesSyncRep;

    public function __invoke(Request $request, RepDirectory $reps, RepPricebook $pricebook): JsonResponse
    {
        $salesRepId = $this->resolveRep($request, $reps);
        $deviceId = $request->string('device_id')->value();

        if ($deviceId !== '') {
            $device = SyncDevice::seenNow($deviceId, $salesRepId);

            SyncAuditLog::query()->create([
                'sync_device_id' => $device->id,
                'direction' => SyncDirection::Pull,
                'entity_type' => 'pricebook',
                'status' => SyncStatus::Ok,
                'occurred_at' => Carbon::now(),
            ]);
        }

        return response()->json([
            'generated_at' => Carbon::now()->toIso8601String(),
            'prices' => $pricebook->forRep($salesRepId),
        ]);
    }
}
