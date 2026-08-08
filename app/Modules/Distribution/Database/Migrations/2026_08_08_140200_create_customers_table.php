<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An outlet: the shop, kiosk or restaurant the rep actually visits.
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('outlet_type', 32);
            $table->string('owner_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('address')->nullable();

            // Captured by the rep's handset when the outlet is registered, so
            // a route can be checked against where the shops actually are.
            // Seven decimal places is roughly centimetre resolution.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->foreignId('route_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('outlet_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
