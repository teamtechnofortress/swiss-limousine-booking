<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->boolean('pickup_locations_only')->default(false)->after('meeting_point_required');
        });
        // Preserve any early meeting-point setup as the first approved pickup for the trip.
        DB::table('trip_locations')->where('role', 'meeting_point')->update(['role' => 'pickup']);
        DB::table('trips')->where('meeting_point_required', true)->update(['pickup_locations_only' => true]);
    }
    public function down(): void
    {
        DB::table('trip_locations')->where('role', 'pickup')->update(['role' => 'meeting_point']);
        Schema::table('trips', function (Blueprint $table): void { $table->dropColumn('pickup_locations_only'); });
    }
};
