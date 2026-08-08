<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();

            // Outlets, reps and routes belong to Distribution, and price lists
            // to Catalog. Orders depends on neither, so these are bare ids
            // with no foreign key — see Docs/adr/0001-module-boundaries.md.
            // Deletions arrive as domain events instead.
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('sales_rep_id')->nullable()->index();
            $table->unsignedBigInteger('route_id')->nullable()->index();

            // Which list priced this order. Phase 3 checks an offline order
            // against the list it was captured under rather than repricing it.
            $table->unsignedBigInteger('price_list_id')->nullable();

            $table->string('status', 16)->index();
            $table->string('currency', 3);

            // Kept in step with the lines rather than derived, so order lists
            // do not each carry a subquery. A test holds the two together.
            $table->unsignedBigInteger('total_minor')->default(0);

            $table->text('notes')->nullable();

            // When the rep took the order, which is not when the row was
            // written: Phase 3 accepts orders hours after the fact.
            $table->timestamp('placed_at');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
