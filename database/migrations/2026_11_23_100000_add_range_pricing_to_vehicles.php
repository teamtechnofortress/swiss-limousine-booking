<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('pricing_mode', 20)->default('simple')->after('minimum_fare');
            // Stored in metres so decimal kilometre boundaries remain exact.
            $table->unsignedInteger('range_base_distance_m')->default(0)->after('pricing_mode');
            $table->unsignedInteger('range_base_fare')->default(0)->after('range_base_distance_m');
        });

        Schema::create('vehicle_distance_price_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            // These are distances beyond the vehicle's included base distance.
            $table->unsignedInteger('start_distance_m');
            $table->unsignedInteger('end_distance_m')->nullable();
            $table->unsignedInteger('minimum_amount')->default(0);
            $table->unsignedInteger('price_per_km');
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();

            $table->unique(['vehicle_id', 'start_distance_m']);
            $table->index(['vehicle_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_distance_price_ranges');

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['pricing_mode', 'range_base_distance_m', 'range_base_fare']);
        });
    }
};
