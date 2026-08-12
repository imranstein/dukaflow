<?php

declare(strict_types=1);

namespace App\Modules\Sync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sync\Http\Requests\PushSyncRequest;
use App\Modules\Sync\Models\SyncDevice;
use App\Modules\Sync\Services\SyncPushHandler;
use App\Support\Contracts\RepDirectory;
use Illuminate\Http\JsonResponse;

class SyncPushController extends Controller
{
    use ResolvesSyncRep;

    public function __invoke(PushSyncRequest $request, RepDirectory $reps, SyncPushHandler $handler): JsonResponse
    {
        $salesRepId = $this->resolveRep($request, $reps);

        $device = SyncDevice::seenNow(
            deviceId: (string) $request->string('device_id'),
            salesRepId: $salesRepId,
            label: $request->string('device_label')->value() ?: null,
        );

        $results = [];

        /** @var array<int, array{client_id?: mixed, entity_type?: mixed, data?: mixed}> $entities */
        $entities = (array) $request->input('entities', []);

        foreach ($entities as $entity) {
            $results[] = $handler->handle($device, $salesRepId, $entity);
        }

        return response()->json([
            'device_id' => $device->device_id,
            'results' => $results,
        ]);
    }
}
