<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which day of the week an outlet is called on, and where it falls in
        // that day's run. One row per outlet per day: an outlet visited twice
        // a week has two.
        Schema::create('visit_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // ISO-8601 numbering, so 1 is Monday and 7 is Sunday, matching
            // Carbon's dayOfWeekIso.
            $table->unsignedTinyInteger('day_of_week');

            $table->unsignedSmallInteger('sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['customer_id', 'day_of_week']);
            $table->index(['day_of_week', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_schedules');
    }
};
