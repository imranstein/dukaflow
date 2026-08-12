<?php

declare(strict_types=1);

namespace App\Modules\Sync\Models;

use App\Modules\Sync\Database\Factories\SyncConflictFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A client_id resubmitted with different content — flagged for a human,
 * never merged. See Docs/adr/0002-offline-sync-strategy.md §2-3.
 *
 * @property int $id
 * @property int $sync_device_id
 * @property string $client_id
 * @property string $entity_type
 * @property string $payload_hash
 * @property bool $resolved
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SyncDevice $device
 */
class SyncConflict extends Model
{
    /** @use HasFactory<SyncConflictFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'sync_device_id',
        'client_id',
        'entity_type',
        'payload_hash',
        'resolved',
        'occurred_at',
    ];

    /** @return BelongsTo<SyncDevice, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(SyncDevice::class, 'sync_device_id');
    }

    /** @return Factory<SyncConflict> */
    protected static function newFactory(): Factory
    {
        return SyncConflictFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'resolved' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }
}
