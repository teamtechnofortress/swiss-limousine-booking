<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('model_name')->nullable();
            $table->string('slug')->unique();
            $table->string('category', 20)->index();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('passenger_capacity');
            $table->unsignedTinyInteger('luggage_capacity');
            $table->unsignedInteger('price_per_km')->nullable();
            $table->unsignedInteger('minimum_fare')->nullable();
            $table->json('amenities')->nullable();
            $table->boolean('slot_check_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
