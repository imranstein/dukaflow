<?php

declare(strict_types=1);

namespace App\Modules\Sync\Database\Factories;

use App\Modules\Sync\Models\SyncConflict;
use App\Modules\Sync\Models\SyncDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SyncConflict> */
class SyncConflictFactory extends Factory
{
    /** @var class-string<SyncConflict> */
    protected $model = SyncConflict::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'sync_device_id' => SyncDevice::factory(),
            'client_id' => (string) Str::ulid(),
            'entity_type' => 'order',
            'payload_hash' => hash('sha256', (string) Str::uuid()),
            'resolved' => false,
            'occurred_at' => now(),
        ];
    }
}
