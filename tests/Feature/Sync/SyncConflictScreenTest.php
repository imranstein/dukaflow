<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Sync\Filament\Resources\SyncConflicts\Pages\ListSyncConflicts;
use App\Modules\Sync\Filament\Resources\SyncConflicts\Pages\ViewSyncConflict;
use App\Modules\Sync\Filament\Resources\SyncConflicts\SyncConflictResource;
use App\Modules\Sync\Models\SyncAuditLog;
use App\Modules\Sync\Models\SyncConflict;
use App\Modules\Sync\Models\SyncDevice;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * Before this, a conflict was only ever visible as a hash in the rep PWA's
 * own badge — see Docs/sync-deep-dive.md. This is the back-office half.
 */

beforeEach(function (): void {
    actingAs(User::factory()->admin()->create());
});

it('does not offer a create form — a conflict is produced by the push handler, not typed in', function () {
    expect(SyncConflictResource::canCreate())->toBeFalse()
        ->and(array_keys(SyncConflictResource::getPages()))->toBe(['index', 'view']);
});

it('lists conflicts with the rejected payload and the winning one side by side', function () {
    $device = SyncDevice::factory()->create(['sales_rep_id' => 7]);
    $conflict = SyncConflict::factory()->for($device, 'device')->create([
        'client_id' => 'conflict-client-id',
        'entity_type' => 'order',
        'rejected_payload' => ['lines' => [['unit_price_minor' => 6000]]],
    ]);
    SyncAuditLog::factory()->create([
        'client_id' => 'conflict-client-id',
        'entity_type' => 'order',
        'response_payload' => ['order_id' => 42, 'reference' => 'SO-2026-00042'],
    ]);

    Livewire::test(ListSyncConflicts::class)
        ->assertCanSeeTableRecords([$conflict]);

    Livewire::test(ViewSyncConflict::class, ['record' => $conflict->getKey()])
        ->assertSee('6000')
        ->assertSee('SO-2026-00042');
});

it('marks a conflict resolved from the table without an edit form', function () {
    $conflict = SyncConflict::factory()->create(['resolved' => false]);

    Livewire::test(ListSyncConflicts::class)
        ->callTableAction('resolve', $conflict);

    expect($conflict->fresh()?->resolved)->toBeTrue();
});
