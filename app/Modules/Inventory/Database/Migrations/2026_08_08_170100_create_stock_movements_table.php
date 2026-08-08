<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The ledger. Rows are inserted and never touched again: a balance is
        // the sum of everything that ever happened to a product at a place,
        // and nothing anywhere stores a current quantity.
        // See Docs/adr/0006-stock-ledger.md.
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();

            // Products belong to Catalog, and a van is a rep, who belongs to
            // Distribution. Inventory depends on neither, so both are bare.
            $table->unsignedBigInteger('product_id');

            // Where the stock is: a warehouse, or the back of a rep's van.
            $table->string('location_type', 16);
            $table->unsignedBigInteger('location_id');

            // Signed: positive in, negative out. One column, no direction
            // flag to get backwards.
            $table->integer('quantity');

            $table->string('type', 24);

            // What caused it, so a balance traces back to its documents.
            $table->string('reference_type', 24)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestamp('occurred_at');
            $table->string('notes')->nullable();

            // Created only. An append-only table has no update to stamp.
            $table->timestamp('created_at')->nullable();

            // The balance query: everything for one product at one place.
            $table->index(['location_type', 'location_id', 'product_id'], 'stock_movements_balance_index');
            $table->index(['reference_type', 'reference_id']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
