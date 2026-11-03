<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('type', 20)->default('transfer')->after('source')->index();
            $table->foreignId('trip_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });
        Schema::table('booking_legs', function (Blueprint $table): void {
            $table->foreignId('pickup_location_id')->nullable()->after('booking_id')->constrained('locations')->nullOnDelete();
            $table->foreignId('dropoff_location_id')->nullable()->after('pickup_location_id')->constrained('locations')->nullOnDelete();
            $table->boolean('pickup_override')->default(false)->after('dropoff_location_id');
            $table->string('pickup_override_reason')->nullable()->after('pickup_override');
        });
    }

    public function down(): void
    {
        Schema::table('booking_legs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pickup_location_id');
            $table->dropConstrainedForeignId('dropoff_location_id');
            $table->dropColumn(['pickup_override', 'pickup_override_reason']);
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('trip_id');
            $table->dropColumn('type');
        });
    }
};
