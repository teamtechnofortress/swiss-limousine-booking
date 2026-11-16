<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->boolean('traveller_limits_enabled')->default(false)->change();
        });

        // Existing trips should use vehicle-derived capacity unless an admin
        // explicitly turns on a trip-specific override again.
        DB::table('trips')->update(['traveller_limits_enabled' => false]);
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->boolean('traveller_limits_enabled')->default(true)->change();
        });
    }
};
