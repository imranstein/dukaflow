<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('product_id')->index();

            // What the product was called when the order was taken. An order
            // is a record of an agreement, not a view of today's catalogue:
            // renaming a product must not rewrite last month's paperwork.
            // See Docs/adr/0005-order-lifecycle.md.
            $table->string('product_sku', 64);
            $table->string('product_name');
            $table->string('unit_code', 8)->nullable();

            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');

            // The list this line's price came from, kept per line because a
            // draft can be repriced one line at a time.
            $table->unsignedBigInteger('price_list_id')->nullable();

            $table->timestamps();

            // One line per product. Ordering more means changing the quantity.
            $table->unique(['order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
