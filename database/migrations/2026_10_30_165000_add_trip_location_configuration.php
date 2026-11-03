<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->boolean('meeting_point_required')->default(false)->after('pricing_mode');
        });
        Schema::create('trip_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('role', 30);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['trip_id', 'location_id', 'role']);
            $table->index(['trip_id', 'role', 'sort_order']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('trip_locations');
        Schema::table('trips', function (Blueprint $table): void { $table->dropColumn('meeting_point_required'); });
    }
};
