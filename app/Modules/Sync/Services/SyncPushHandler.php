<?php

declare(strict_types=1);

namespace App\Modules\Sync\Services;

use App\Modules\Sync\Enums\SyncDirection;
use App\Modules\Sync\Enums\SyncStatus;
use App\Modules\Sync\Models\SyncAuditLog;
use App\Modules\Sync\Models\SyncConflict;
use App\Modules\Sync\Models\SyncDevice;
use App\Support\Contracts\OrderIntake;
use App\Support\Contracts\VisitOutcomeIntake;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * One pushed entity in, one result out. The idempotency rule from
 * Docs/adr/0002-offline-sync-strategy.md §2: same id and same content is a
 * no-op replay; same id and different content is a conflict, written
 * nowhere; a genuinely new id is submitted through the matching intake
 * contract and its outcome becomes the record for next time.
 */
final readonly class SyncPushHandler
{
    public function __construct(
        private OrderIntake $orders,
        private VisitOutcomeIntake $visits,
    ) {}

    /**
     * @param  array{client_id?: mixed, entity_type?: mixed, data?: mixed}  $entity
     * @return array{client_id: string|null, entity_type: string, status: string, data: array<string, mixed>|null, message: string|null}
     */
    public function handle(SyncDevice $device, int $salesRepId, array $entity): array
    {
        $clientId = is_string($entity['client_id'] ?? null) ? $entity['client_id'] : null;
        $entityType = is_string($entity['entity_type'] ?? null) ? $entity['entity_type'] : '';
        /** @var array<string, mixed> $data */
        $data = is_array($entity['data'] ?? null) ? $entity['data'] : [];

        if ($clientId === null || $clientId === '') {
            return $this->result($clientId, $entityType, SyncStatus::Error, null, 'A client_id is required to push.');
        }

        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        $existing = SyncAuditLog::forClientId($clientId, $entityType);

        if ($existing !== null) {
            return $existing->matchesHash($hash)
                ? $this->result($clientId, $entityType, SyncStatus::Ok, $existing->response_payload, null)
                : $this->recordConflict($device, $clientId, $entityType, $hash);
        }

        try {
            $response = match ($entityType) {
                'order' => $this->submitOrder($clientId, $salesRepId, $data),
                'visit_outcome' => $this->submitVisitOutcome($clientId, $salesRepId, $data),
                default => throw new InvalidArgumentException("Unknown entity type [{$entityType}]."),
            };
        } catch (Throwable $failure) {
            // Not written to the audit log: an error is not "already
            // processed," and a device that fixes its payload should be
            // free to try again under the same client_id.
            return $this->result($clientId, $entityType, SyncStatus::Error, null, $failure->getMessage());
        }

        SyncAuditLog::query()->create([
            'sync_device_id' => $device->id,
            'direction' => SyncDirection::Push,
            'entity_type' => $entityType,
            'client_id' => $clientId,
            'payload_hash' => $hash,
            'status' => SyncStatus::Ok,
            'response_payload' => $response,
            'occurred_at' => Carbon::now(),
        ]);

        return $this->result($clientId, $entityType, SyncStatus::Ok, $response, null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function submitOrder(string $clientId, int $salesRepId, array $data): array
    {
        /** @var list<array{product_id: int, quantity: int, unit_price_minor: int, price_list_id: int|null}> $lines */
        $lines = [];

        foreach ((array) ($data['lines'] ?? []) as $line) {
            $lines[] = [
                'product_id' => (int) $line['product_id'],
                'quantity' => (int) $line['quantity'],
                'unit_price_minor' => (int) $line['unit_price_minor'],
                'price_list_id' => isset($line['price_list_id']) ? (int) $line['price_list_id'] : null,
            ];
        }

        return $this->orders->submit(
            clientId: $clientId,
            customerId: (int) $data['customer_id'],
            // The rep is who the request authenticated as, never a value
            // the payload claims — one device does not get to place an
            // order as another rep.
            salesRepId: $salesRepId,
            routeId: isset($data['route_id']) ? (int) $data['route_id'] : null,
            placedAt: Carbon::parse((string) $data['placed_at']),
            currency: (string) $data['currency'],
            lines: $lines,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function submitVisitOutcome(string $clientId, int $salesRepId, array $data): array
    {
        return $this->visits->record(
            clientId: $clientId,
            customerId: (int) $data['customer_id'],
            salesRepId: $salesRepId,
            routeId: isset($data['route_id']) ? (int) $data['route_id'] : null,
            outcome: (string) $data['outcome'],
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            orderId: isset($data['order_id']) ? (int) $data['order_id'] : null,
            orderReference: isset($data['order_reference']) ? (string) $data['order_reference'] : null,
            occurredAt: Carbon::parse((string) $data['occurred_at']),
        );
    }

    /** @return array{client_id: string|null, entity_type: string, status: string, data: array<string, mixed>|null, message: string|null} */
    private function recordConflict(SyncDevice $device, string $clientId, string $entityType, string $hash): array
    {
        SyncConflict::query()->create([
            'sync_device_id' => $device->id,
            'client_id' => $clientId,
            'entity_type' => $entityType,
            'payload_hash' => $hash,
            'occurred_at' => Carbon::now(),
        ]);

        return $this->result(
            $clientId,
            $entityType,
            SyncStatus::Conflict,
            null,
            'This id was already used for different content. Flagged for review, not merged.',
        );
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array{client_id: string|null, entity_type: string, status: string, data: array<string, mixed>|null, message: string|null}
     */
    private function result(?string $clientId, string $entityType, SyncStatus $status, ?array $data, ?string $message): array
    {
        return [
            'client_id' => $clientId,
            'entity_type' => $entityType,
            'status' => $status->value,
            'data' => $data,
            'message' => $message,
        ];
    }
}
