<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_list_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();

            // Which kind of thing the list is attached to, and its id.
            //
            // There is deliberately no foreign key here. Customers and routes
            // belong to the Distribution module, and Catalog does not depend
            // on Distribution in either direction — see
            // Docs/adr/0001-module-boundaries.md. The cost of that boundary is
            // that the database cannot enforce this reference, so Distribution
            // raises a domain event when a customer or route is deleted and
            // Catalog cleans up its own assignments.
            $table->string('scope', 16);
            $table->unsignedBigInteger('scope_id');

            $table->timestamps();

            $table->unique(['price_list_id', 'scope', 'scope_id']);
            $table->index(['scope', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_assignments');
    }
};
