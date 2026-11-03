<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_extras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_addon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('price_minor');
            $table->unsignedTinyInteger('quantity');
            $table->unsignedInteger('total_minor');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('booking_extras'); }
};
