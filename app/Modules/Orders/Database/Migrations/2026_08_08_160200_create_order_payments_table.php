<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A note that money arrived, by cash or on credit. There is no
        // gateway here and there will not be one: the brief makes payment
        // integrations a hard boundary.
        Schema::create('order_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('method', 16);
            $table->unsignedBigInteger('amount_minor');
            $table->date('received_on');

            // Whatever the rep wrote on the receipt book.
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
