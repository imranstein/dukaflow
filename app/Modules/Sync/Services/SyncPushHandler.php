<?php

declare(strict_types=1);

namespace App\Modules\Sync\Services;

use App\Modules\Sync\Enums\SyncDirection;
use App\Modules\Sync\Enums\SyncStatus;
use App\Modules\Sync\Exceptions\SyncPushException;
use App\Modules\Sync\Models\SyncAuditLog;
use App\Modules\Sync\Models\SyncConflict;
use App\Modules\Sync\Models\SyncDevice;
use App\Support\Contracts\OrderIntake;
use App\Support\Contracts\RepDirectory;
use App\Support\Contracts\VisitOutcomeIntake;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * One pushed entity in, one result out. The idempotency rule from
 * Docs/adr/0002-offline-sync-strategy.md §2: same id and same content is a
 * no-op replay; same id and different content is a conflict, written
 * nowhere; a genuinely new id is submitted through the matching intake
 * contract and its outcome becomes the record for next time.
 *
 * The entity write and the audit log row that makes it replayable are
 * written in one transaction — a response lost between the two would
 * otherwise leave a client_id that can never be resubmitted, only ever
 * fail against the entity's own uniqueness. See the failure this was
 * written to close in the review that found it.
 */
final readonly class SyncPushHandler
{
    public function __construct(
        private OrderIntake $orders,
        private VisitOutcomeIntake $visits,
        private RepDirectory $reps,
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

        try {
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            // Caught here rather than left to bubble: one entity with a
            // malformed payload must fail on its own, not 500 the whole
            // batch the other entities in this request are riding along in.
            return $this->result($clientId, $entityType, SyncStatus::Error, null, 'This entity could not be read.');
        }

        $existing = SyncAuditLog::forClientId($clientId, $entityType);

        if ($existing !== null) {
            return $this->replay($existing, $device, $salesRepId, $clientId, $entityType, $data, $hash);
        }

        return DB::transaction(fn (): array => $this->submitNew($device, $salesRepId, $clientId, $entityType, $data, $hash));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{client_id: string|null, entity_type: string, status: string, data: array<string, mixed>|null, message: string|null}
     */
    private function replay(
        SyncAuditLog $existing,
        SyncDevice $device,
        int $salesRepId,
        string $clientId,
        string $entityType,
        array $data,
        string $hash,
    ): array {
        if (! $existing->matchesHash($hash)) {
            return $this->recordConflict($device, $clientId, $entityType, $data, $hash);
        }

        if ($existing->device->sales_rep_id !== $salesRepId) {
            // Same id, same content, but asked for by a different rep than
            // whoever originally submitted it. Not a legitimate replay of
            // your own work — refuse rather than hand back someone else's
            // order.
            return $this->result($clientId, $entityType, SyncStatus::Error, null, 'This id belongs to a different device.');
        }

        return $this->result($clientId, $entityType, SyncStatus::Ok, $existing->response_payload, null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{client_id: string|null, entity_type: string, status: string, data: array<string, mixed>|null, message: string|null}
     */
    private function submitNew(
        SyncDevice $device,
        int $salesRepId,
        string $clientId,
        string $entityType,
        array $data,
        string $hash,
    ): array {
        try {
            $response = match ($entityType) {
                'order' => $this->submitOrder($clientId, $salesRepId, $data),
                'visit_outcome' => $this->submitVisitOutcome($clientId, $salesRepId, $data),
                default => throw new InvalidArgumentException("Unknown entity type [{$entityType}]."),
            };
        } catch (Throwable $failure) {
            // A concurrent request may have already won this id while ours
            // was in flight — re-check rather than assume. If it has, this
            // is the same conflict a resubmitted id gets, not a generic
            // error the device would otherwise retry forever.
            $wonByAnother = SyncAuditLog::forClientId($clientId, $entityType);

            if ($wonByAnother !== null) {
                return $wonByAnother->matchesHash($hash)
                    ? $this->result($clientId, $entityType, SyncStatus::Ok, $wonByAnother->response_payload, null)
                    : $this->recordConflict($device, $clientId, $entityType, $data, $hash);
            }

            return $this->result($clientId, $entityType, SyncStatus::Error, null, $this->safeMessage($failure));
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
        $customerId = (int) $data['customer_id'];
        $this->assertOwnsCustomer($salesRepId, $customerId);

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
            customerId: $customerId,
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
        $customerId = (int) $data['customer_id'];
        $this->assertOwnsCustomer($salesRepId, $customerId);

        return $this->visits->record(
            clientId: $clientId,
            customerId: $customerId,
            salesRepId: $salesRepId,
            routeId: isset($data['route_id']) ? (int) $data['route_id'] : null,
            outcome: (string) $data['outcome'],
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            orderId: isset($data['order_id']) ? (int) $data['order_id'] : null,
            orderReference: isset($data['order_reference']) ? (string) $data['order_reference'] : null,
            occurredAt: Carbon::parse((string) $data['occurred_at']),
        );
    }

    /**
     * A device only gets to act for customers on its own rep's route — see
     * Docs/adr/0002-offline-sync-strategy.md §8. Checked here rather than
     * left to the pull's own scoping, since a device that already cached a
     * customer before losing that route, or one that simply sends an id it
     * was never handed, must not be trusted either way.
     */
    private function assertOwnsCustomer(int $salesRepId, int $customerId): void
    {
        if (! $this->reps->ownsCustomer($salesRepId, $customerId)) {
            throw SyncPushException::customerNotOnRoute();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{client_id: string|null, entity_type: string, status: string, data: array<string, mixed>|null, message: string|null}
     */
    private function recordConflict(SyncDevice $device, string $clientId, string $entityType, array $data, string $hash): array
    {
        SyncConflict::query()->create([
            'sync_device_id' => $device->id,
            'client_id' => $clientId,
            'entity_type' => $entityType,
            'payload_hash' => $hash,
            'rejected_payload' => $data,
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
     * A DomainException (or InvalidArgumentException) is one of this
     * project's own controlled, intentionally-worded rejections — safe to
     * show as-is. Anything else is a real bug or an infrastructure hiccup;
     * its message might name a table or a constraint, so it is logged for
     * whoever reads the server's error log and replaced with something a
     * device can show a rep.
     */
    private function safeMessage(Throwable $failure): string
    {
        if ($failure instanceof DomainException || $failure instanceof InvalidArgumentException) {
            return $failure->getMessage();
        }

        report($failure);

        return 'Something went wrong processing this entity. It will be retried.';
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
