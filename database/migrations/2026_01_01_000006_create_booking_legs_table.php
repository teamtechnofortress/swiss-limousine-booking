<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);
            $table->json('pickup');
            $table->json('dropoff');
            $table->dateTime('starts_at')->index();
            $table->unsignedInteger('distance_m');
            $table->unsignedInteger('duration_s');
            $table->unsignedInteger('amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_legs');
    }
};
