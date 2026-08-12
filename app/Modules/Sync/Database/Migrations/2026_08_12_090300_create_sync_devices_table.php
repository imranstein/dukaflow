<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A device is a ULID the PWA generates once and keeps in
        // localStorage. This exists to give the audit log something to key
        // on and the sync status screen something to show, not to manage a
        // fleet of hardware — see Docs/adr/0002-offline-sync-strategy.md §9.
        Schema::create('sync_devices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('device_id')->unique();

            // Distribution's own table; a bare id for the same reason
            // orders carries one, see Docs/adr/0001-module-boundaries.md.
            $table->unsignedBigInteger('sales_rep_id')->index();

            $table->string('label')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_devices');
    }
};
