<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every sync exchange, and the idempotency store for pushes both at
        // once — see Docs/adr/0002-offline-sync-strategy.md §2. A push row
        // is looked up by (client_id, entity_type) before anything is
        // written: found with a matching hash, the stored response replays;
        // found with a different hash, nothing is written and the mismatch
        // becomes a sync_conflicts row instead, because this table's unique
        // constraint means a second row for the same id cannot exist here.
        Schema::create('sync_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_device_id')->constrained();

            $table->string('direction', 8);
            $table->string('entity_type', 32);

            // Null on a pull row, which has no client-assigned identity.
            $table->ulid('client_id')->nullable();
            $table->string('payload_hash', 64)->nullable();

            $table->string('status', 16);
            $table->json('response_payload')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['client_id', 'entity_type']);
            $table->index(['sync_device_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_audit_log');
    }
};
