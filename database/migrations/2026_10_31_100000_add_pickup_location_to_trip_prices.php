<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trip_prices', function (Blueprint $table): void {
            $table->dropUnique(['trip_id', 'vehicle_id']);
            $table->foreignId('pickup_location_id')->nullable()->after('trip_id')->constrained('locations')->nullOnDelete();
            $table->unique(['trip_id', 'pickup_location_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::table('trip_prices', function (Blueprint $table): void {
            $table->dropUnique(['trip_id', 'pickup_location_id', 'vehicle_id']);
            $table->dropConstrainedForeignId('pickup_location_id');
            $table->unique(['trip_id', 'vehicle_id']);
        });
    }
};
