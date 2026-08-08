<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('currency', 3)->default('ETB');

            // A price list is in force between these dates. Re-pricing means
            // closing one list and opening the next, which leaves the prices
            // an old order was captured under still readable. Phase 3 relies
            // on that when it validates offline orders.
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_lists');
    }
};
