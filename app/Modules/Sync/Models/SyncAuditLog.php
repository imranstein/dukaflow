<?php

declare(strict_types=1);

namespace App\Modules\Sync\Models;

use App\Modules\Sync\Database\Factories\SyncAuditLogFactory;
use App\Modules\Sync\Enums\SyncDirection;
use App\Modules\Sync\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per sync exchange, and the idempotency store for pushes at the
 * same time — see Docs/adr/0002-offline-sync-strategy.md §2.
 *
 * @property int $id
 * @property int $sync_device_id
 * @property SyncDirection $direction
 * @property string $entity_type
 * @property string|null $client_id
 * @property string|null $payload_hash
 * @property SyncStatus $status
 * @property array<string, mixed>|null $response_payload
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SyncDevice $device
 */
class SyncAuditLog extends Model
{
    /** @use HasFactory<SyncAuditLogFactory> */
    use HasFactory;

    protected $table = 'sync_audit_log';

    /** @var list<string> */
    protected $fillable = [
        'sync_device_id',
        'direction',
        'entity_type',
        'client_id',
        'payload_hash',
        'status',
        'response_payload',
        'occurred_at',
    ];

    /**
     * The idempotency check itself: a push already recorded under this id.
     * Null means the id is genuinely new to the server.
     */
    public static function forClientId(string $clientId, string $entityType): ?self
    {
        return self::query()
            ->where('client_id', $clientId)
            ->where('entity_type', $entityType)
            ->first();
    }

    public function matchesHash(string $hash): bool
    {
        return $this->payload_hash === $hash;
    }

    /** @return BelongsTo<SyncDevice, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(SyncDevice::class, 'sync_device_id');
    }

    /** @return Factory<SyncAuditLog> */
    protected static function newFactory(): Factory
    {
        return SyncAuditLogFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'direction' => SyncDirection::class,
            'status' => SyncStatus::class,
            'response_payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
