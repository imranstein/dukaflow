<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The original migration stored only the hash of a rejected push —
        // provable that content differed, but not what. A back-office
        // conflicts queue where the reviewer can't see the rejected content
        // is barely a queue; store it alongside the hash.
        Schema::table('sync_conflicts', function (Blueprint $table): void {
            $table->json('rejected_payload')->nullable()->after('payload_hash');
        });
    }

    public function down(): void
    {
        Schema::table('sync_conflicts', function (Blueprint $table): void {
            $table->dropColumn('rejected_payload');
        });
    }
};
