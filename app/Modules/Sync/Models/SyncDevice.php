<?php

declare(strict_types=1);

namespace App\Modules\Sync\Models;

use App\Modules\Sync\Database\Factories\SyncDeviceFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A phone the PWA has installed on. Its identity is the ULID it generated
 * for itself, kept alongside every audit log row it produced.
 *
 * @property int $id
 * @property string $device_id
 * @property int $sales_rep_id
 * @property string|null $label
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SyncDevice extends Model
{
    /** @use HasFactory<SyncDeviceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['device_id', 'sales_rep_id', 'label', 'last_seen_at'];

    /**
     * The device behind an id, registering itself on first contact if this
     * is the first the server has heard of it. Called on every exchange, so
     * last_seen_at always reflects the truth without a separate heartbeat.
     */
    public static function seenNow(string $deviceId, int $salesRepId, ?string $label = null): self
    {
        $device = self::query()->firstOrNew(['device_id' => $deviceId]);
        $device->sales_rep_id = $salesRepId;
        $device->last_seen_at = Carbon::now();

        if ($label !== null) {
            $device->label = $label;
        }

        $device->save();

        return $device;
    }

    /** @return Factory<SyncDevice> */
    protected static function newFactory(): Factory
    {
        return SyncDeviceFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
