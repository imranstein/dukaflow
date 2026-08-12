<?php

declare(strict_types=1);

namespace App\Modules\Sync\Database\Factories;

use App\Modules\Sync\Enums\SyncDirection;
use App\Modules\Sync\Enums\SyncStatus;
use App\Modules\Sync\Models\SyncAuditLog;
use App\Modules\Sync\Models\SyncDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SyncAuditLog> */
class SyncAuditLogFactory extends Factory
{
    /** @var class-string<SyncAuditLog> */
    protected $model = SyncAuditLog::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'sync_device_id' => SyncDevice::factory(),
            'direction' => SyncDirection::Push,
            'entity_type' => 'order',
            'client_id' => (string) Str::ulid(),
            'payload_hash' => hash('sha256', (string) Str::uuid()),
            'status' => SyncStatus::Ok,
            'response_payload' => ['order_id' => 1, 'reference' => 'SO-2026-00001'],
            'occurred_at' => now(),
        ];
    }

    public function pull(string $entityType): self
    {
        return $this->state([
            'direction' => SyncDirection::Pull,
            'entity_type' => $entityType,
            'client_id' => null,
            'payload_hash' => null,
            'response_payload' => null,
        ]);
    }
}
