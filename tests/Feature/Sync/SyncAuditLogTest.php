<?php

declare(strict_types=1);

use App\Modules\Sync\Enums\SyncDirection;
use App\Modules\Sync\Enums\SyncStatus;
use App\Modules\Sync\Models\SyncAuditLog;
use App\Modules\Sync\Models\SyncDevice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/*
 * The audit log doubles as the idempotency store, per
 * Docs/adr/0002-offline-sync-strategy.md §2. This is the lookup a push
 * handler leans on before writing anything.
 */

it('finds nothing for an id the server has never seen', function () {
    expect(SyncAuditLog::forClientId((string) Str::ulid(), 'order'))->toBeNull();
});

it('finds a prior push by client id and entity type, not by id alone', function () {
    $clientId = (string) Str::ulid();

    $row = SyncAuditLog::factory()->create([
        'client_id' => $clientId,
        'entity_type' => 'order',
        'payload_hash' => 'abc123',
        'status' => SyncStatus::Ok,
    ]);

    expect(SyncAuditLog::forClientId($clientId, 'order')?->is($row))->toBeTrue()
        ->and(SyncAuditLog::forClientId($clientId, 'visit_outcome'))->toBeNull();
});

it('tells a matching resubmission from a mismatched one', function () {
    $row = SyncAuditLog::factory()->create(['payload_hash' => 'abc123']);

    expect($row->matchesHash('abc123'))->toBeTrue()
        ->and($row->matchesHash('xyz789'))->toBeFalse();
});

it('registers a device on first contact and updates it on the next', function () {
    $deviceId = (string) Str::ulid();

    Carbon::setTestNow('2026-08-12 08:00:00');
    $first = SyncDevice::seenNow($deviceId, salesRepId: 7, label: 'Phone A');

    Carbon::setTestNow('2026-08-12 14:00:00');
    $again = SyncDevice::seenNow($deviceId, salesRepId: 7);

    expect(SyncDevice::query()->count())->toBe(1)
        ->and($first->id)->toBe($again->id)
        ->and($again->label)->toBe('Phone A')
        ->and($first->last_seen_at?->toDateTimeString())->toBe('2026-08-12 08:00:00')
        ->and($again->last_seen_at?->toDateTimeString())->toBe('2026-08-12 14:00:00');

    Carbon::setTestNow();
});

it('finds the row a push wrote for replay, distinct by direction', function () {
    SyncAuditLog::factory()->pull('product')->create();
    $pushed = SyncAuditLog::factory()->create(['entity_type' => 'order']);

    expect($pushed->direction)->toBe(SyncDirection::Push)
        ->and(SyncAuditLog::query()->where('direction', SyncDirection::Pull)->count())->toBe(1);
});
