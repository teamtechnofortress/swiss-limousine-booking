<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_locations', function (Blueprint $table): void {
            // Only approved pickup rows use this. Destinations and itinerary stops do not.
            $table->unsignedInteger('duration_minutes')->nullable()->after('role');
        });

        // Preserve the duration already configured for each existing approved pickup.
        DB::table('trip_locations')->where('role', 'pickup')->orderBy('id')->eachById(function (object $link): void {
            $duration = DB::table('trips')->where('id', $link->trip_id)->value('duration_minutes');
            DB::table('trip_locations')->where('id', $link->id)->update(['duration_minutes' => $duration]);
        });

        Schema::table('trips', function (Blueprint $table): void {
            $table->dropColumn('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->unsignedInteger('duration_minutes')->nullable()->after('region');
        });

        DB::table('trips')->orderBy('id')->eachById(function (object $trip): void {
            $duration = DB::table('trip_locations')
                ->where('trip_id', $trip->id)
                ->where('role', 'pickup')
                ->min('duration_minutes');
            DB::table('trips')->where('id', $trip->id)->update(['duration_minutes' => $duration ?? 480]);
        });

        Schema::table('trip_locations', function (Blueprint $table): void {
            $table->dropColumn('duration_minutes');
        });
    }
};
