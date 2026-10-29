<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('fleet_unit_id')->nullable()->constrained('fleet_units')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('booking_leg_id')->nullable()->constrained('booking_legs')->cascadeOnDelete();
            $table->string('block_type', 10);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'starts_at', 'ends_at']);
            $table->index(['block_type', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_blocks');
    }
};
