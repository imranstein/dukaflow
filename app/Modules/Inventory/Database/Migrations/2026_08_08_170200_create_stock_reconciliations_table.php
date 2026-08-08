<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // End of day: the van is counted, and the count is compared with what
        // the ledger says should be there.
        Schema::create('stock_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sales_rep_id')->index();
            $table->date('reconciled_on');
            $table->string('status', 16);
            $table->timestamp('closed_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            // One count per rep per day.
            $table->unique(['sales_rep_id', 'reconciled_on']);
        });

        Schema::create('stock_reconciliation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');

            // What the ledger said, and what was actually on the van. The
            // difference is reported, never silently applied: closing the
            // reconciliation is what writes the adjustments.
            $table->integer('expected_quantity');
            $table->integer('counted_quantity');

            $table->timestamps();

            $table->unique(['stock_reconciliation_id', 'product_id'], 'reconciliation_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reconciliation_lines');
        Schema::dropIfExists('stock_reconciliations');
    }
};
