<?php

declare(strict_types=1);

namespace App\Modules\Sync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sync\Enums\SyncDirection;
use App\Modules\Sync\Enums\SyncStatus;
use App\Modules\Sync\Http\Requests\PullSyncRequest;
use App\Modules\Sync\Models\SyncAuditLog;
use App\Modules\Sync\Models\SyncDevice;
use App\Support\CompositeSyncFeed;
use App\Support\Contracts\RepDirectory;
use App\Support\SyncCursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class SyncPullController extends Controller
{
    use ResolvesSyncRep;

    private const int DEFAULT_LIMIT = 200;

    public function __invoke(PullSyncRequest $request, RepDirectory $reps, CompositeSyncFeed $feed): JsonResponse
    {
        $salesRepId = $this->resolveRep($request, $reps);
        $entityType = (string) $request->string('entity_type');

        if (! $feed->handles($entityType)) {
            abort(422, "Nothing pulls [{$entityType}].");
        }

        $device = SyncDevice::seenNow((string) $request->string('device_id'), $salesRepId);

        $cursorToken = $request->string('cursor')->value() ?: null;

        try {
            $cursor = SyncCursor::decode($cursorToken);
        } catch (InvalidArgumentException) {
            // A device's local cursor got corrupted or the format changed
            // under it. A clean 422 lets it discard the cursor and re-pull
            // from scratch; a 500 would read as the server being down.
            abort(422, 'That cursor is not valid. Drop it and pull again from the start.');
        }

        $limit = (int) ($request->integer('limit') ?: self::DEFAULT_LIMIT);

        $rows = $feed->pull($entityType, $cursor, $limit, $salesRepId);

        $lastRow = end($rows);
        $nextCursor = $lastRow === false
            ? $cursorToken
            : (new SyncCursor(Carbon::parse($lastRow['updated_at']), $lastRow['id']))->encode();

        SyncAuditLog::query()->create([
            'sync_device_id' => $device->id,
            'direction' => SyncDirection::Pull,
            'entity_type' => $entityType,
            'status' => SyncStatus::Ok,
            'occurred_at' => Carbon::now(),
        ]);

        return response()->json([
            'entity_type' => $entityType,
            'rows' => $rows,
            'next_cursor' => $nextCursor,
            'has_more' => count($rows) === $limit,
        ]);
    }
}
