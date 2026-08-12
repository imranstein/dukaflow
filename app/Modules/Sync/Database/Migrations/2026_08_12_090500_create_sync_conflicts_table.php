<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A client_id reused for different content — the one conflict this
        // design has to handle, per Docs/adr/0002-offline-sync-strategy.md
        // §2-3. Flagged here for a human, never merged, and never written to
        // the record it disagreed with.
        Schema::create('sync_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_device_id')->constrained();

            $table->ulid('client_id');
            $table->string('entity_type', 32);
            $table->string('payload_hash', 64);

            $table->boolean('resolved')->default(false);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['client_id', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
    }
};
