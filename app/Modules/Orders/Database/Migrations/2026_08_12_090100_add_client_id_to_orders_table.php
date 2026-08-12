<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // The device's own id for an order captured offline, per
            // Docs/adr/0003-id-strategy.md. Null for an order keyed in
            // directly at the back office, which has no device.
            $table->ulid('client_id')->nullable()->unique()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('client_id');
        });
    }
};
