<?php

declare(strict_types=1);

namespace App\Modules\Sync\Database\Factories;

use App\Modules\Sync\Models\SyncDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SyncDevice> */
class SyncDeviceFactory extends Factory
{
    /** @var class-string<SyncDevice> */
    protected $model = SyncDevice::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'device_id' => (string) Str::ulid(),

            // A bare id: Sync does not know what module this belongs to.
            'sales_rep_id' => fake()->numberBetween(1, 20),

            'label' => 'Android — Chrome',
            'last_seen_at' => now(),
        ];
    }
}
