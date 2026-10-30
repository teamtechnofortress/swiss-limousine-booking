<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('provider', 20);
            $table->string('provider_reference')->nullable()->unique();
            $table->string('provider_payment_intent')->nullable()->index();
            $table->char('idempotency_key', 64)->unique();
            $table->string('status', 20)->index();
            $table->boolean('is_current')->default(true)->index();
            $table->dateTime('expires_at')->nullable()->index();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'is_current', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
