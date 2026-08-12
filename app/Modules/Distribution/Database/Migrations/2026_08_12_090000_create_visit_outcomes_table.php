<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What actually happened when a rep called on an outlet. A row here
        // is written once, by the device that captured it, and never edited
        // after — see Docs/adr/0002-offline-sync-strategy.md §3.
        Schema::create('visit_outcomes', function (Blueprint $table): void {
            $table->id();

            // The device's own id for this record, per ADR-003. Null for a
            // visit outcome entered directly in the back office, which is
            // not the normal path but is not forbidden either.
            $table->ulid('client_id')->nullable()->unique();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_rep_id')->constrained();
            $table->foreignId('route_id')->nullable()->constrained()->nullOnDelete();

            $table->string('outcome', 16);
            $table->string('reason')->nullable();

            // Orders belongs to a different module, so this is a bare id and
            // a snapshot of its reference, the same pattern stock_movements
            // uses to point at an order without depending on it. Null unless
            // the outcome is order_placed.
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('order_reference', 32)->nullable();

            // When the rep says it happened, from the device's own clock.
            $table->timestamp('occurred_at');

            // When the server actually saw it. Null for a row entered
            // directly rather than pushed through sync. A large gap between
            // the two is expected and logged, not treated as an error.
            $table->timestamp('received_at')->nullable();

            $table->timestamps();

            $table->index(['sales_rep_id', 'occurred_at']);
            $table->index(['customer_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_outcomes');
    }
};
