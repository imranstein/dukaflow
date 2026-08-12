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
            // Set when a synced order's captured prices disagree with what
            // the pricebook says now, per Docs/adr/0002-offline-sync-strategy.md
            // §5. The order keeps the price the rep actually quoted; this is
            // only a flag for a manager to look at, never a silent reprice.
            $table->boolean('has_price_variance')->default(false)->after('price_list_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('has_price_variance');
        });
    }
};
