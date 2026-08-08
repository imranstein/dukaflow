<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('unit_of_measure_id')
                ->nullable()
                ->after('name')
                ->constrained('units_of_measure')
                ->nullOnDelete();

            // How many base units are in one selling unit: 24 bottles to a
            // crate, 12 cartons to a case. Reps order in selling units.
            $table->unsignedInteger('pack_size')->default(1)->after('unit_of_measure_id');
            $table->string('category')->nullable()->after('pack_size');
            $table->string('barcode', 64)->nullable()->unique()->after('category');

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_of_measure_id');
            $table->dropIndex(['category']);
            $table->dropColumn(['pack_size', 'category', 'barcode']);
        });
    }
};
